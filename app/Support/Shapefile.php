<?php

namespace App\Support;

/**
 * Minimaler Shapefile-Leser für Polygon-Ebenen (Shape-Typ 5), z.B. BKG VG250.
 * Im Container gibt es kein GDAL/ogr2ogr; das Format ist einfach genug, um
 * es direkt zu lesen: .shp = Geometrie (Little/Big Endian gemischt),
 * .dbf = Attribute (dBASE III, feste Spaltenbreiten, Kodierung laut .cpg).
 *
 * records() ist ein Generator, damit auch die 31-MB-Gemeindeebene nicht
 * komplett im Speicher landet.
 */
final class Shapefile
{
    /**
     * @param string $basis Pfad ohne Endung, z.B. /srv/daten/vg250/2023-12-31/VG250_STA
     * @return \Generator<int, array{attr: array<string, string>, rings: list<list<array{0: float, 1: float}>>}>
     */
    public static function records(string $basis): \Generator
    {
        $shp = fopen($basis . '.shp', 'rb');
        $dbf = fopen($basis . '.dbf', 'rb');
        if ($shp === false || $dbf === false) {
            throw new \RuntimeException("Shapefile nicht lesbar: $basis");
        }
        [$felder, $anzahl, $kopfLaenge, $satzLaenge] = self::dbfKopf($dbf);
        fseek($dbf, $kopfLaenge);
        fseek($shp, 100);

        for ($i = 0; $i < $anzahl; $i++) {
            $kopf = fread($shp, 8);
            if ($kopf === false || strlen($kopf) < 8) {
                break;
            }
            $inhaltBytes = unpack('N2', $kopf)[2] * 2;
            $inhalt = fread($shp, $inhaltBytes);
            $attr = self::dbfSatz(fread($dbf, $satzLaenge), $felder);
            yield ['attr' => $attr, 'rings' => self::polygonRinge($inhalt)];
        }
        fclose($shp);
        fclose($dbf);
    }

    /** @return list<list<array{0: float, 1: float}>> Ringe in Dateireihenfolge (äußere im Uhrzeigersinn, Löcher gegen) */
    private static function polygonRinge(string $inhalt): array
    {
        $typ = unpack('V', $inhalt, 0)[1];
        if ($typ === 0) {
            return [];
        }
        if ($typ !== 5) {
            throw new \RuntimeException("Shape-Typ $typ nicht unterstützt (nur Polygon = 5)");
        }
        ['p' => $teile, 'n' => $punkte] = unpack('Vp/Vn', $inhalt, 36);
        $starts = array_values(unpack("V$teile", $inhalt, 44));
        $starts[] = $punkte;
        $koord = unpack('e' . (2 * $punkte), $inhalt, 44 + 4 * $teile);
        $ringe = [];
        for ($t = 0; $t < $teile; $t++) {
            $ring = [];
            for ($k = $starts[$t]; $k < $starts[$t + 1]; $k++) {
                $ring[] = [$koord[2 * $k + 1], $koord[2 * $k + 2]];
            }
            $ringe[] = $ring;
        }
        return $ringe;
    }

    /** @return array{0: list<array{0: string, 1: int}>, 1: int, 2: int, 3: int} */
    private static function dbfKopf($dbf): array
    {
        $kopf = fread($dbf, 32);
        ['n' => $anzahl, 'h' => $kopfLaenge, 'r' => $satzLaenge] = unpack('Vn/vh/vr', $kopf, 4);
        $felder = [];
        while (true) {
            $f = fread($dbf, 32);
            if ($f === false || $f[0] === "\x0D" || strlen($f) < 32) {
                break;
            }
            $felder[] = [rtrim(substr($f, 0, 11), "\0 "), ord($f[16])];
        }
        return [$felder, $anzahl, $kopfLaenge, $satzLaenge];
    }

    /** @param list<array{0: string, 1: int}> $felder */
    private static function dbfSatz(string $satz, array $felder): array
    {
        $attr = [];
        $pos = 1; // Byte 0 = Löschmarke
        foreach ($felder as [$name, $breite]) {
            $attr[$name] = trim(substr($satz, $pos, $breite));
            $pos += $breite;
        }
        return $attr;
    }
}
