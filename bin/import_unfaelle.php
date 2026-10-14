<?php
/**
 * Import des Unfallatlas (Issue #20): alle polizeilich erfassten Straßen-
 * verkehrsunfälle mit Personenschaden, punktgenau, in die Tabelle unfaelle.
 * Ab 2021 sind alle 16 Länder enthalten (NRW erst ab 2019, MV ab 2020).
 *
 * Quelle: https://www.opengeodata.nrw.de/produkte/transport_verkehr/unfallatlas/
 * (UnfallorteJJJJ_EPSG25832_CSV.zip, Semikolon, Dezimalkomma, Koordinaten
 * zusätzlich in WGS84). Lizenz dl-de/by-2.0, Quellvermerk
 * "Statistische Ämter des Bundes und der Länder, Unfallatlas".
 * Gelesen wird über PHPs ZipArchive (im Container gibt es kein unzip).
 *
 * Voraussetzung: Migration sql/022_unfaelle.sql
 *
 * Aufruf: php bin/import_unfaelle.php [--jahre=2021-2025] [--cache=DIR] [--dry-run]
 *   --jahre    Jahre, z.B. 2021-2025 oder 2024 (Standard 2021-2025)
 *   --cache    Ordner für die Downloads (Standard /srv/daten/unfallatlas)
 *   --dry-run  nur laden und zählen, nichts schreiben
 *   --help     nur diese Hilfe
 */

require __DIR__ . '/../app/Support/Db.php';

use App\Support\Db;

const BASIS = 'https://www.opengeodata.nrw.de/produkte/transport_verkehr/unfallatlas/';

$opt = getopt('', ['jahre:', 'cache:', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$cache = rtrim($opt['cache'] ?? '/srv/daten/unfallatlas', '/');
$dryRun = isset($opt['dry-run']);
[$von, $bis] = array_map('intval', explode('-', ($opt['jahre'] ?? '2021-2025') . (str_contains($opt['jahre'] ?? '-', '-') ? '' : '-' . ($opt['jahre'] ?? ''))));
if (!is_dir($cache) && !mkdir($cache, 0775, true)) {
    fwrite(STDERR, "Ordner nicht anlegbar: $cache\n");
    exit(1);
}

$db = $dryRun ? null : Db::get();
$ins = $db?->prepare('INSERT INTO unfaelle (uid, jahr, monat, stunde, kategorie, rad, fuss, pkw, krad, gkfz, lat, lon, pt)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?, ST_GeomFromText(?))
    ON DUPLICATE KEY UPDATE kategorie = VALUES(kategorie)');

$start = microtime(true);
for ($jahr = $von; $jahr <= $bis; $jahr++) {
    $datei = "$cache/Unfallorte$jahr.zip";
    if (!is_file($datei)) {
        $inhalt = @file_get_contents(BASIS . "Unfallorte{$jahr}_EPSG25832_CSV.zip");
        if ($inhalt === false) {
            fwrite(STDERR, "Download fehlgeschlagen: $jahr\n");
            exit(1);
        }
        file_put_contents($datei, $inhalt);
    }
    $zip = new ZipArchive();
    if ($zip->open($datei) !== true) {
        fwrite(STDERR, "Zip nicht lesbar: $datei\n");
        exit(1);
    }
    // Die Jahrgänge sind unterschiedlich verpackt (2021: .txt, sonst .csv, mal
    // mit, mal ohne Unterordner, Spalten in wechselnder Reihenfolge) — die
    // größte Datendatei nehmen und über die Spaltennamen lesen.
    $csvName = null;
    $groesse = -1;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        if (preg_match('/\.(csv|txt)$/i', $st['name']) && $st['size'] > $groesse) {
            [$csvName, $groesse] = [$st['name'], $st['size']];
        }
    }
    if ($csvName === null) {
        fwrite(STDERR, "Keine CSV in $datei\n");
        exit(1);
    }
    $stream = $zip->getStream($csvName);
    $kopf = str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', trim(fgets($stream))), ';');
    $idx = array_flip($kopf);
    foreach (['UIDENTSTLAE', 'UJAHR', 'UMONAT', 'USTUNDE', 'UKATEGORIE', 'IstRad', 'IstFuss', 'IstPKW', 'IstKrad', 'IstGkfz', 'XGCSWGS84', 'YGCSWGS84'] as $pflicht) {
        if (!isset($idx[$pflicht])) {
            fwrite(STDERR, "Spalte $pflicht fehlt in $csvName (Spalten: " . implode(',', $kopf) . ")\n");
            exit(1);
        }
    }
    if ($db !== null) {
        $db->beginTransaction();
        $db->prepare('DELETE FROM unfaelle WHERE jahr = ?')->execute([$jahr]);
    }
    $n = 0;
    $proKat = [1 => 0, 2 => 0, 3 => 0];
    while (($z = fgets($stream)) !== false) {
        $s = explode(';', rtrim($z, "\r\n"));
        if (count($s) < count($kopf)) {
            continue;
        }
        $lon = (float) str_replace(',', '.', $s[$idx['XGCSWGS84']]);
        $lat = (float) str_replace(',', '.', $s[$idx['YGCSWGS84']]);
        $kat = (int) $s[$idx['UKATEGORIE']];
        $n++;
        $proKat[$kat] = ($proKat[$kat] ?? 0) + 1;
        $ins?->execute([
            $s[$idx['UIDENTSTLAE']], (int) $s[$idx['UJAHR']], (int) $s[$idx['UMONAT']], (int) $s[$idx['USTUNDE']], $kat,
            (int) $s[$idx['IstRad']], (int) $s[$idx['IstFuss']], (int) $s[$idx['IstPKW']], (int) $s[$idx['IstKrad']], (int) $s[$idx['IstGkfz']],
            $lat, $lon, sprintf('POINT(%.7F %.7F)', $lon, $lat),
        ]);
    }
    fclose($stream);
    $zip->close();
    if ($db !== null) {
        if ($n < 10000) {
            $db->rollBack();
            fwrite(STDERR, "$jahr: nur $n Unfälle gelesen — abgebrochen, alte Werte bleiben.\n");
            exit(1);
        }
        $db->commit();
    }
    printf("%d: %d Unfälle (Getötete %d, Schwerverletzte %d, Leichtverletzte %d), %.0f s\n", $jahr, $n, $proKat[1], $proKat[2], $proKat[3], microtime(true) - $start);
}
echo $dryRun ? "Testlauf: nichts geschrieben.\n" : "Fertig.\n";
