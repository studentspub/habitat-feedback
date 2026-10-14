<?php
/**
 * Import der DWD-Klimaraster (1 km, Jahreswerte der Normalperioden 1961-1990
 * und 1991-2020) in klima_raster (Issue #19). Damit zeigt die Klima-Karte
 * heiße Tage, Frost, Starkregen usw. genau am Ort statt an der nächsten
 * Wetterstation, und daneben den Wert von früher.
 *
 * Quelle: https://opendata.dwd.de/climate_environment/CDC/grids_germany/multi_annual/
 * (ESRI-ASCII-Raster, Gauß-Krüger Zone 3), Lizenz CC BY 4.0, Quelle "Deutscher Wetterdienst".
 * Die Dateien werden unter --cache abgelegt und bei erneutem Aufruf wiederverwendet.
 *
 * Voraussetzung: Migration sql/021_klima_raster.sql
 *
 * Aufruf: php bin/import_dwd_raster.php [--cache=DIR] [--dry-run]
 *   --cache    Ordner für die Downloads (Standard /srv/daten/dwd)
 *   --dry-run  nur laden und zählen, nichts in die DB schreiben
 *   --help     nur diese Hilfe
 */

require __DIR__ . '/../app/Support/Db.php';

use App\Support\Db;

const BASIS = 'https://opendata.dwd.de/climate_environment/CDC/grids_germany/multi_annual/';
// Spalte in klima_raster => DWD-Ordner
const PARAMETER = [
    'heiss' => 'hot_days',
    'sommer' => 'summer_days',
    'frost' => 'frost_days',
    'eis' => 'ice_days',
    'starkregen' => 'precipGE20mm_days',
    'schnee' => 'snowcover_days',
    'sonne' => 'sunshine_duration',
];
const PERIODEN = ['6190' => '1961-1990', '9120' => '1991-2020'];

$opt = getopt('', ['cache:', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$cache = rtrim($opt['cache'] ?? '/srv/daten/dwd', '/');
$dryRun = isset($opt['dry-run']);
if (!is_dir($cache) && !mkdir($cache, 0775, true)) {
    fwrite(STDERR, "Ordner nicht anlegbar: $cache\n");
    exit(1);
}

/** @return array{kopf: array<string, float>, werte: list<list<?float>>} */
function ladeRaster(string $ordner, string $periode, string $cache): array
{
    $name = "grids_germany_multi_annual_{$ordner}_{$periode}_17.asc.gz";
    $datei = "$cache/$name";
    if (!is_file($datei)) {
        $ctx = stream_context_create(['http' => ['header' => "User-Agent: habitat-feedback-import/1.0\r\n", 'timeout' => 60]]);
        $inhalt = file_get_contents(BASIS . "$ordner/$name", false, $ctx);
        if ($inhalt === false) {
            fwrite(STDERR, "Download fehlgeschlagen: $name\n");
            exit(1);
        }
        file_put_contents($datei, $inhalt);
    }
    $zeilen = gzfile($datei);
    $kopf = [];
    $werte = [];
    foreach ($zeilen as $z) {
        $z = trim($z);
        if ($z === '') {
            continue;
        }
        if (preg_match('/^([A-Za-z_]+)\s+(-?[\d.]+)$/', $z, $m)) {
            $kopf[strtoupper($m[1])] = (float) $m[2];
            continue;
        }
        $nodata = $kopf['NODATA_VALUE'] ?? -999;
        $werte[] = array_map(fn($v) => (float) $v == $nodata ? null : (float) $v, preg_split('/\s+/', $z));
    }
    return ['kopf' => $kopf, 'werte' => $werte];
}

$start = microtime(true);
$gitter = null;
$daten = []; // "spalte" => [zeile => [feld => wert]] wird zu flachem Array
foreach (PARAMETER as $feld => $ordner) {
    foreach (PERIODEN as $suffix => $periode) {
        $r = ladeRaster($ordner, $periode, $cache);
        $k = $r['kopf'];
        $geo = [$k['XLLCORNER'], $k['YLLCORNER'], $k['CELLSIZE'], (int) $k['NCOLS'], (int) $k['NROWS']];
        if ($gitter === null) {
            $gitter = $geo;
        } elseif ($geo != $gitter) {
            fwrite(STDERR, "Rastergeometrie von $ordner $periode weicht ab — Abbruch.\n");
            exit(1);
        }
        $mitWert = 0;
        foreach ($r['werte'] as $zeile => $reihe) {
            foreach ($reihe as $spalte => $wert) {
                if ($wert !== null) {
                    $daten[$zeile * 10000 + $spalte]["{$feld}_{$suffix}"] = $wert;
                    $mitWert++;
                }
            }
        }
        printf("  %-18s %s: %d Zellen mit Wert\n", $ordner, $periode, $mitWert);
    }
}
printf("Zellen gesamt: %d, Gitter %d×%d à %.0f m, %.0f s\n", count($daten), $gitter[3], $gitter[4], $gitter[2], microtime(true) - $start);

if ($dryRun) {
    echo "Testlauf: nichts geschrieben.\n";
    exit(0);
}

$felder = [];
foreach (array_keys(PARAMETER) as $f) {
    foreach (array_keys(PERIODEN) as $s) {
        $felder[] = "{$f}_{$s}";
    }
}
$db = Db::get();
$db->beginTransaction();
$db->exec('DELETE FROM klima_raster');
$db->exec('DELETE FROM klima_raster_gitter');
$db->prepare('INSERT INTO klima_raster_gitter (id, xll, yll, zellgroesse, spalten, zeilen) VALUES (1, ?, ?, ?, ?, ?)')->execute($gitter);
$puffer = [];
$spaltenSql = 'spalte, zeile, ' . implode(', ', $felder);
$platz = '(' . implode(',', array_fill(0, count($felder) + 2, '?')) . ')';
$flush = function () use (&$puffer, $db, $spaltenSql, $platz): void {
    if ($puffer === []) {
        return;
    }
    $db->prepare("INSERT INTO klima_raster ($spaltenSql) VALUES " . implode(',', array_fill(0, count($puffer), $platz)))
        ->execute(array_merge(...$puffer));
    $puffer = [];
};
foreach ($daten as $schluessel => $w) {
    $row = [$schluessel % 10000, intdiv($schluessel, 10000)];
    foreach ($felder as $f) {
        $row[] = $w[$f] ?? null;
    }
    $puffer[] = $row;
    if (count($puffer) >= 1000) {
        $flush();
    }
}
$flush();
$db->commit();
printf("klima_raster: %d Zellen geschrieben, %.0f s\n", count($daten), microtime(true) - $start);
