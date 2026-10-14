<?php
/**
 * Erzeugt public/data/de_grenze.geojson — die Landesgrenze Deutschlands für
 * die Nebel-Maske der Karte (Issue #12). Quelle: BKG VG250, Ebene STA
 * (Staat), nur Landflächen (GF = 4), vereinfacht nach Douglas-Peucker in
 * Metern (UTM 32), dann in Grad umgerechnet.
 *
 * Läuft einmalig per Hand im Container (dort liegt VG250 unter
 * /srv/daten/vg250/<Stand>/), Ergebnis wird ins Repo übernommen.
 *
 * Aufruf: php bin/build_de_grenze.php [--quelle=DIR] [--toleranz=40] [--min-flaeche=0.05] [--ausgabe=DATEI] [--dry-run]
 *   --quelle       Ordner mit VG250_STA.shp/.dbf (Standard /srv/daten/vg250/2023-12-31)
 *   --toleranz     Vereinfachung in Metern (Standard 40)
 *   --min-flaeche  kleinere Ringe (km²) fallen weg, z.B. Sandbänke (Standard 0.05)
 *   --ausgabe      Zieldatei (Standard public/data/de_grenze.geojson)
 *   --dry-run      nur Statistik, nichts schreiben
 *   --help         nur diese Hilfe
 *
 * Quellvermerk: © GeoBasis-DE / BKG (2023), dl-de/by-2.0
 */

require __DIR__ . '/../app/Support/Shapefile.php';
require __DIR__ . '/../app/Support/Utm32.php';

use App\Support\Shapefile;
use App\Support\Utm32;

$opt = getopt('', ['quelle:', 'toleranz:', 'min-flaeche:', 'ausgabe:', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$quelle = rtrim($opt['quelle'] ?? '/srv/daten/vg250/2023-12-31', '/');
$toleranz = (float) ($opt['toleranz'] ?? 40);
$minFlaeche = (float) ($opt['min-flaeche'] ?? 0.05) * 1e6;
$ausgabe = $opt['ausgabe'] ?? __DIR__ . '/../public/data/de_grenze.geojson';
$dryRun = isset($opt['dry-run']);

/** Douglas-Peucker, iterativ (Ringe haben bis zu ~100.000 Punkte). */
function vereinfachen(array $p, float $tol): array
{
    $n = count($p);
    if ($n < 5) {
        return $p;
    }
    $behalten = array_fill(0, $n, false);
    $behalten[0] = $behalten[$n - 1] = true;
    $stapel = [[0, $n - 1]];
    $tol2 = $tol * $tol;
    while ($stapel) {
        [$a, $b] = array_pop($stapel);
        [$ax, $ay] = $p[$a];
        [$bx, $by] = $p[$b];
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len2 = $dx * $dx + $dy * $dy;
        $maxD = -1.0;
        $idx = -1;
        for ($i = $a + 1; $i < $b; $i++) {
            [$x, $y] = $p[$i];
            if ($len2 == 0.0) {
                $d = ($x - $ax) ** 2 + ($y - $ay) ** 2;
            } else {
                $t = max(0, min(1, (($x - $ax) * $dx + ($y - $ay) * $dy) / $len2));
                $d = ($x - $ax - $t * $dx) ** 2 + ($y - $ay - $t * $dy) ** 2;
            }
            if ($d > $maxD) {
                $maxD = $d;
                $idx = $i;
            }
        }
        if ($idx >= 0 && $maxD > $tol2) {
            $behalten[$idx] = true;
            $stapel[] = [$a, $idx];
            $stapel[] = [$idx, $b];
        }
    }
    $out = [];
    foreach ($p as $i => $pt) {
        if ($behalten[$i]) {
            $out[] = $pt;
        }
    }
    return $out;
}

function flaeche(array $ring): float
{
    $s = 0.0;
    $n = count($ring);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $s += ($ring[$j][0] * $ring[$i][1]) - ($ring[$i][0] * $ring[$j][1]);
    }
    return abs($s / 2);
}

$ringe = [];
$punkteVorher = $punkteNachher = $wegKlein = 0;
foreach (Shapefile::records("$quelle/VG250_STA") as $rec) {
    if (($rec['attr']['GF'] ?? '') !== '4') {
        continue; // nur Land, keine Küstengewässer
    }
    foreach ($rec['rings'] as $ring) {
        $punkteVorher += count($ring);
        if (flaeche($ring) < $minFlaeche) {
            $wegKlein++;
            continue;
        }
        $v = vereinfachen($ring, $toleranz);
        if (count($v) < 4) {
            $wegKlein++;
            continue;
        }
        $punkteNachher += count($v);
        $ringe[] = array_map(function ($pt) {
            [$lat, $lon] = Utm32::toLatLon($pt[0], $pt[1]);
            return [round($lon, 5), round($lat, 5)];
        }, $v);
    }
}

// Jeder Ring als eigenes Polygon: die Karte legt alle Ringe als Löcher in ein
// Welt-Rechteck (Füllregel evenodd), Enklaven innerhalb Deutschlands werden
// dadurch automatisch wieder zur Maske.
$geojson = json_encode([
    'type' => 'FeatureCollection',
    'features' => [[
        'type' => 'Feature',
        'properties' => [
            'quelle' => '© GeoBasis-DE / BKG (2023), dl-de/by-2.0, VG250 Stand 31.12.2023, vereinfacht ' . $toleranz . ' m',
        ],
        'geometry' => ['type' => 'MultiPolygon', 'coordinates' => array_map(fn($r) => [$r], $ringe)],
    ]],
], JSON_UNESCAPED_UNICODE);

printf("Ringe: %d (weggelassen klein: %d), Punkte: %d -> %d, Datei: %.0f KB, gzip: %.0f KB\n",
    count($ringe), $wegKlein, $punkteVorher, $punkteNachher, strlen($geojson) / 1024, strlen(gzencode($geojson, 9)) / 1024);

if ($dryRun) {
    echo "Testlauf: nichts geschrieben.\n";
    exit(0);
}
if (!is_dir(dirname($ausgabe))) {
    mkdir(dirname($ausgabe), 0775, true);
}
file_put_contents($ausgabe, $geojson);
echo "Geschrieben: $ausgabe\n";
