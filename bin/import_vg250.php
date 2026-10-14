<?php
/**
 * Import der Gemeinde- und Kreisgrenzen aus BKG VG250 in gebiete_gemeinde /
 * gebiete_kreis (Issue #17). Damit bestimmt die Seite lokal per ST_Contains,
 * in welcher Gemeinde und welchem Kreis ein Punkt liegt (statt Nominatim-
 * Rückwärtssuche), und kennt den amtlichen Gemeindeschlüssel (AGS) für
 * Gemeinde-Kennzahlen (INKAR, Issue #18).
 *
 * Nur Landflächen (GF = 4). Jeder Kreis bekommt zusätzlich den Namen, unter
 * dem die bestehenden Kreis-Tabellen (Arbeitslosigkeit, PKS, GENESIS …)
 * gespeichert sind (KreisNames::match, gleiche Logik wie bisher für Nominatim).
 *
 * Voraussetzung: Migration sql/019_gebiete.sql, VG250 entpackt unter --quelle
 * (Download: https://daten.gdz.bkg.bund.de/produkte/vg/vg250_ebenen_1231/2023/vg250_12-31.utm32s.shape.ebenen.zip).
 *
 * Aufruf: php bin/import_vg250.php [--quelle=DIR] [--dry-run]
 *   --quelle   Ordner mit VG250_KRS.* und VG250_GEM.* (Standard /srv/daten/vg250/2023-12-31)
 *   --dry-run  nur lesen, umrechnen und zählen, nichts in die DB schreiben
 *   --help     nur diese Hilfe
 *
 * Quellvermerk: © GeoBasis-DE / BKG (2023), dl-de/by-2.0
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/Shapefile.php';
require __DIR__ . '/../app/Support/Utm32.php';

use App\Support\Db;
use App\Support\KreisNames;
use App\Support\Shapefile;
use App\Support\Utm32;

$opt = getopt('', ['quelle:', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$quelle = rtrim($opt['quelle'] ?? '/srv/daten/vg250/2023-12-31', '/');
$dryRun = isset($opt['dry-run']);

/**
 * Shapefile-Ringe -> WKT-MULTIPOLYGON in Grad. Im Shapefile laufen äußere
 * Ringe im Uhrzeigersinn, Löcher dagegen und folgen ihrem äußeren Ring.
 */
function multipolygonWkt(array $ringe): string
{
    $polygone = [];
    foreach ($ringe as $ring) {
        $s = 0.0;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $s += $ring[$j][0] * $ring[$i][1] - $ring[$i][0] * $ring[$j][1];
        }
        $punkte = implode(',', array_map(function ($p) {
            [$lat, $lon] = Utm32::toLatLon($p[0], $p[1]);
            return sprintf('%.6F %.6F', $lon, $lat);
        }, $ring));
        if ($s < 0 || $polygone === []) {
            $polygone[] = ["($punkte)"];          // äußerer Ring (im Uhrzeigersinn)
        } else {
            $polygone[count($polygone) - 1][] = "($punkte)"; // Loch des letzten äußeren Rings
        }
    }
    return 'MULTIPOLYGON(' . implode(',', array_map(fn($p) => '(' . implode(',', $p) . ')', $polygone)) . ')';
}

$db = $dryRun ? null : Db::get();
if ($db !== null) {
    $db->exec('DELETE FROM gebiete_gemeinde');
    $db->exec('DELETE FROM gebiete_kreis');
    $insKreis = $db->prepare('INSERT INTO gebiete_kreis (ags, name, bez, kreis_name, geom) VALUES (:ags, :name, :bez, :kreis_name, ST_GeomFromText(:wkt))');
    $insGem = $db->prepare('INSERT INTO gebiete_gemeinde (ags, name, bez, kreis_ags, geom) VALUES (:ags, :name, :bez, :kreis_ags, ST_GeomFromText(:wkt))');
}

$start = microtime(true);
$kreise = 0;
$ohneName = [];
foreach (Shapefile::records("$quelle/VG250_KRS") as $rec) {
    $a = $rec['attr'];
    if ($a['GF'] !== '4') {
        continue;
    }
    $istStadt = in_array($a['BEZ'], ['Kreisfreie Stadt', 'Stadtkreis'], true);
    $kreisName = $istStadt ? KreisNames::match(null, $a['GEN']) : KreisNames::match($a['GEN'], null);
    if ($kreisName === null) {
        $ohneName[] = $a['AGS'] . ' ' . $a['GEN'] . ' (' . $a['BEZ'] . ')';
    }
    $kreise++;
    if ($db !== null) {
        $insKreis->execute(['ags' => $a['AGS'], 'name' => $a['GEN'], 'bez' => $a['BEZ'], 'kreis_name' => $kreisName, 'wkt' => multipolygonWkt($rec['rings'])]);
    }
}
printf("Kreise: %d (%.0f s), ohne Zuordnung zu den Kreis-Tabellen: %d\n", $kreise, microtime(true) - $start, count($ohneName));
foreach ($ohneName as $k) {
    echo "  - $k\n";
}

$gemeinden = 0;
foreach (Shapefile::records("$quelle/VG250_GEM") as $rec) {
    $a = $rec['attr'];
    if ($a['GF'] !== '4') {
        continue;
    }
    $gemeinden++;
    if ($db !== null) {
        $insGem->execute(['ags' => $a['AGS'], 'name' => $a['GEN'], 'bez' => $a['BEZ'], 'kreis_ags' => substr($a['AGS'], 0, 5), 'wkt' => multipolygonWkt($rec['rings'])]);
    }
    if ($gemeinden % 2000 === 0) {
        printf("  … %d Gemeinden\n", $gemeinden);
    }
}
printf("Gemeinden: %d, gesamt %.0f s%s\n", $gemeinden, microtime(true) - $start, $dryRun ? ' (Testlauf: nichts geschrieben)' : '');
