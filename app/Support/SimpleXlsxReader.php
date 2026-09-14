<?php

namespace App\Support;

/**
 * Minimaler, abhängigkeitsfreier XLSX-Leser für Import-Skripte. Eine XLSX-
 * Datei ist technisch nur ein ZIP-Archiv aus XML-Dateien — PHPs eingebaute
 * ZipArchive + SimpleXML reichen also, ohne eine Composer-Bibliothek wie
 * PhpSpreadsheet einzuführen (passt zum Rest des Projekts: PHP pur, kein
 * Framework, keine neue Abhängigkeit nur für einen einmaligen Datenimport).
 *
 * Deckt bewusst nur das ab, was die BKA/Destatis-Publikationen tatsächlich
 * brauchen: benanntes Arbeitsblatt lesen, Zeilen als einfache Werte-Arrays
 * (0-indiziert nach Spalte, Lücken durch leere Zellen als null). Keine
 * Formeln, keine Formatierung, keine mehrzeiligen Runs mit Formatwechsel
 * innerhalb einer Zelle.
 */
final class SimpleXlsxReader
{
    /** @return array<int, array<int, string|float|null>> */
    public static function readSheet(string $path, string $sheetName): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Konnte XLSX nicht öffnen: $path");
        }

        $sharedStrings = self::readSharedStrings($zip);
        $sheetTarget = self::resolveSheetTarget($zip, $sheetName);
        $sheetXml = $zip->getFromName('xl/' . $sheetTarget);
        $zip->close();

        if ($sheetXml === false) {
            throw new \RuntimeException("Arbeitsblatt '$sheetName' nicht gefunden in $path");
        }

        return self::parseRows($sheetXml, $sharedStrings);
    }

    /** @return array<int, string> */
    private static function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return []; // Datei ohne Textzellen hat manchmal gar keine sharedStrings.xml
        }
        $doc = new \SimpleXMLElement($xml);
        $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($doc->si as $si) {
            // Text kann direkt in <t> stehen oder auf mehrere <r><t>-Runs
            // (z.B. bei fett/normal gemischtem Text) aufgeteilt sein.
            $strings[] = $si->r === null || count($si->r) === 0
                ? (string) $si->t
                : implode('', array_map(static fn ($r) => (string) $r->t, iterator_to_array($si->r)));
        }
        return $strings;
    }

    private static function resolveSheetTarget(\ZipArchive $zip, string $sheetName): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            throw new \RuntimeException('workbook.xml oder workbook.xml.rels fehlt.');
        }

        $workbook = new \SimpleXMLElement($workbookXml);
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rId = null;
        foreach ($workbook->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] === $sheetName) {
                $rId = (string) $sheet->attributes('r', true)['id'];
                break;
            }
        }
        if ($rId === null) {
            throw new \RuntimeException("Arbeitsblatt '$sheetName' steht nicht in workbook.xml.");
        }

        $rels = new \SimpleXMLElement($relsXml);
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rId) {
                return (string) $rel['Target'];
            }
        }
        throw new \RuntimeException("Keine Relationship für $rId gefunden.");
    }

    /** @return array<int, array<int, string|float|null>> */
    private static function parseRows(string $sheetXml, array $sharedStrings): array
    {
        $doc = new \SimpleXMLElement($sheetXml);
        $rows = [];
        foreach ($doc->sheetData->row as $rowEl) {
            $row = [];
            foreach ($rowEl->c as $cellEl) {
                $colIndex = self::columnIndexFromRef((string) $cellEl['r']);
                $type = (string) $cellEl['t'];
                $value = match ($type) {
                    's' => $sharedStrings[(int) $cellEl->v] ?? null,
                    'inlineStr' => (string) $cellEl->is->t,
                    'str' => (string) $cellEl->v,
                    default => $cellEl->v !== null && (string) $cellEl->v !== '' ? (float) $cellEl->v : null,
                };
                $row[$colIndex] = $value;
            }
            if ($row !== []) {
                ksort($row);
                $maxIndex = array_key_last($row);
                $rows[] = array_map(static fn (int $i) => $row[$i] ?? null, range(0, $maxIndex));
            } else {
                $rows[] = [];
            }
        }
        return $rows;
    }

    /** Wandelt eine Zellreferenz wie "C10" in einen 0-basierten Spaltenindex (2). */
    private static function columnIndexFromRef(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $letters = $m[1] ?? 'A';
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }
        return $index - 1;
    }
}
