<?php
/**
 * Straßenverzeichnis aus OpenStreetMap für die Adress-Autovervollständigung
 * (Issue #15): eine Zeile je Straße + PLZ + Ort, ohne Hausnummern. Damit
 * kommen die Vorschläge beim Tippen aus der eigenen DB statt live von
 * Nominatim (dessen Nutzungsrichtlinie Autocomplete über die API verbietet).
 *
 * Ablauf:
 *  1. osmium filtert aus dem Extrakt benannte Straßen (highway=residential,
 *     living_street, unclassified, tertiary, secondary, primary, trunk,
 *     pedestrian, road), Adresspunkte (addr:street) und PLZ-Gebiete
 *     (boundary=postal_code) nach /srv/daten/osm/arbeit/. Die Teilauszüge
 *     werden wiederverwendet, solange sie neuer als der Extrakt sind.
 *  2. Adresspunkte werden zu Straße + PLZ + Ort zusammengefasst; Schreibweise
 *     und Ort sind jeweils die häufigsten. Fehlt die PLZ, kommt sie aus den
 *     PLZ-Gebieten, fehlt der Ort, wird es die Gemeinde.
 *  3. Benannte Straßen ohne eine einzige Adresse gleichen Namens in der Nähe
 *     (Neubaugebiete, Ortsdurchfahrten) kommen dazu, PLZ und Ort genauso.
 *  4. Jede Zeile bekommt ihre Gemeinde (VG250, Issue #17); was außerhalb
 *     Deutschlands liegt (der Extrakt reicht über die Grenze), fällt weg.
 *
 * Die Auswertung braucht osmium und ~8 GB RAM (WSL). Der Container importiert
 * stattdessen das Ergebnis (--export in WSL, --ergebnis im Container), wie bei
 * GTFS (#24). Die neue Tabelle wird erst vollständig gefüllt und dann gegen die
 * alte getauscht; bei zu wenigen Zeilen bleibt die alte stehen.
 *
 * Voraussetzung: Migrationen sql/019_gebiete.sql (mit Daten aus
 * bin/import_vg250.php) und sql/025_strassen.sql.
 * Extrakt: curl -L -o /srv/daten/osm/germany-latest.osm.pbf https://download.geofabrik.de/europe/germany-latest.osm.pbf
 *
 * Aufruf: php bin/import_strassen.php [--pbf=DATEI] [--export=DATEI.csv.gz] [--neu-filtern] [--dry-run]
 *         php bin/import_strassen.php --ergebnis=DATEI.csv.gz     (nur das fertige Ergebnis einspielen)
 *   --pbf          OSM-Extrakt (Standard /srv/daten/osm/germany-latest.osm.pbf)
 *   --export       Ergebnis zusätzlich als .csv.gz schreiben (für den Container)
 *   --ergebnis     statt OSM-Auswertung ein mit --export geschriebenes Ergebnis importieren
 *   --neu-filtern  Teilauszüge in /srv/daten/osm/arbeit/ neu erzeugen, auch wenn sie aktuell sind
 *   --dry-run      auswerten und zählen, nichts in die DB schreiben (nur eine temporäre Hilfstabelle)
 *   --help         nur diese Hilfe
 *
 * Quellvermerk: © OpenStreetMap-Mitwirkende, ODbL
 */

ini_set('memory_limit', '16G');
require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/StrassenSuche.php';

use App\Support\Db;
use App\Support\StrassenSuche;

const OSM_ORDNER = '/srv/daten/osm';
const STRASSEN_TYPEN = 'residential,living_street,unclassified,tertiary,secondary,primary,trunk,pedestrian,road';
const ZELLE_LAT = 0.02;   // ~2,2 km
const ZELLE_LON = 0.03;   // ~2 km
const PROBE = 8;          // Adresspunkte je Gruppe, aus denen der Kartenpunkt gewählt wird
const MINDEST_ZEILEN = 500000;

$opt = getopt('', ['pbf:', 'export:', 'ergebnis:', 'neu-filtern', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$dryRun = isset($opt['dry-run']);
$start = microtime(true);
$log = function (string $t) use ($start): void {
    printf("[%4.0f s, %5.0f MB] %s\n", microtime(true) - $start, memory_get_usage() / 1048576, $t);
};

/**
 * Füllt strassen_neu und tauscht sie gegen strassen (bei zu wenigen Zeilen
 * bleibt die alte Tabelle). $zeilen: [name, plz, ort, gemeinde_ags, lat, lon, adressen]
 */
function speichern(iterable $zeilen, string $osmStand, string $quelle): int
{
    $db = Db::get();
    $db->exec('DROP TABLE IF EXISTS strassen_neu');
    $db->exec('CREATE TABLE strassen_neu LIKE strassen');
    $puffer = [];
    $anzahl = 0;
    $flush = function () use (&$puffer, $db): void {
        if ($puffer === []) {
            return;
        }
        $db->prepare('INSERT INTO strassen_neu (name, name_norm, plz, ort, ort_norm, gemeinde_ags, lat, lon, adressen) VALUES '
            . implode(',', array_fill(0, count($puffer), '(?,?,?,?,?,?,?,?,?)')))->execute(array_merge(...$puffer));
        $puffer = [];
    };
    foreach ($zeilen as [$name, $plz, $ort, $ags, $lat, $lon, $adressen]) {
        $puffer[] = [mb_substr($name, 0, 150), substr(StrassenSuche::norm($name), 0, 150), $plz ?: null, mb_substr($ort, 0, 100),
            substr(StrassenSuche::norm($ort), 0, 100), $ags ?: null, round($lat, 5), round($lon, 5), $adressen];
        $anzahl++;
        if (count($puffer) >= 2000) {
            $flush();
        }
    }
    $flush();
    if ($anzahl < MINDEST_ZEILEN) {
        $db->exec('DROP TABLE strassen_neu');
        fwrite(STDERR, "Nur $anzahl Zeilen — abgebrochen, alte Daten bleiben.\n");
        exit(1);
    }
    $db->exec('DROP TABLE IF EXISTS strassen_alt');
    $db->exec('RENAME TABLE strassen TO strassen_alt, strassen_neu TO strassen');
    $db->exec('DROP TABLE strassen_alt');
    $db->beginTransaction();
    $db->exec('DELETE FROM strassen_stand');
    $db->prepare('INSERT INTO strassen_stand (id, osm_stand, quelle) VALUES (1, ?, ?)')->execute([$osmStand, $quelle]);
    $db->commit();
    return $anzahl;
}

// ---------------------------------------------------------------- nur Ergebnis einspielen (Container)
if (isset($opt['ergebnis'])) {
    $fh = fopen('compress.zlib://' . $opt['ergebnis'], 'r');
    $kopf = fgetcsv($fh, null, ';', '"', '');
    [, $osmStand, $quelle] = explode('|', $kopf[0]);
    $zeilen = function () use ($fh) {
        while (($z = fgetcsv($fh, null, ';', '"', '')) !== false) {
            yield [$z[0], $z[1], $z[2], $z[3], (float) $z[4], (float) $z[5], (int) $z[6]];
        }
    };
    if ($dryRun) {
        $n = 0;
        foreach ($zeilen() as $_) {
            $n++;
        }
        $log("$n Zeilen im Ergebnis (OSM-Stand $osmStand), Testlauf: nichts geschrieben");
        exit(0);
    }
    $n = speichern($zeilen(), $osmStand, $quelle);
    fclose($fh);
    $log("$n Straßen gespeichert (OSM-Stand $osmStand)");
    exit(0);
}

// ---------------------------------------------------------------- OSM auswerten
$pbf = $opt['pbf'] ?? OSM_ORDNER . '/germany-latest.osm.pbf';
if (!is_file($pbf)) {
    fwrite(STDERR, "OSM-Extrakt $pbf fehlt. Download: curl -L -o $pbf https://download.geofabrik.de/europe/germany-latest.osm.pbf\n");
    exit(1);
}
if (trim((string) shell_exec('command -v osmium')) === '') {
    fwrite(STDERR, "osmium fehlt (apt install osmium-tool) — die Auswertung läuft in WSL, im Container --ergebnis verwenden.\n");
    exit(1);
}
preg_match('/timestamp=(\d{4}-\d{2}-\d{2})/', (string) shell_exec('osmium fileinfo ' . escapeshellarg($pbf)), $m);
$osmStand = $m[1] ?? date('Y-m-d', filemtime($pbf));
$log("Extrakt $pbf, Stand $osmStand");

$arbeit = OSM_ORDNER . '/arbeit';
@mkdir($arbeit, 0755, true);
$auszuege = [
    'strassen' => 'w/highway=' . STRASSEN_TYPEN,
    'adressen' => 'nw/addr:street',
    'plz' => 'r/boundary=postal_code',
];
foreach ($auszuege as $name => $ausdruck) {
    $ziel = "$arbeit/$name.osm.pbf";
    if (!isset($opt['neu-filtern']) && is_file($ziel) && filemtime($ziel) >= filemtime($pbf)) {
        continue;
    }
    $log("osmium tags-filter $ausdruck");
    exec('osmium tags-filter ' . escapeshellarg($pbf) . ' ' . escapeshellarg($ausdruck) . ' -o ' . escapeshellarg($ziel) . ' -O --no-progress', $_, $rc);
    if ($rc !== 0) {
        fwrite(STDERR, "osmium tags-filter fehlgeschlagen ($rc).\n");
        exit(1);
    }
}

/**
 * Liest einen Teilauszug als GeoJSON-Zeilen; ruft $f(geometrie, tags) je Objekt.
 * $linien: Wege als Linien (Straßen), sonst geschlossene Wege und Relationen als
 * Flächen (Gebäude, PLZ-Gebiete) — beides zugleich gäbe jedes Gebäude doppelt.
 */
function objekte(string $datei, array $tags, bool $linien, callable $f): int
{
    $cfg = tempnam(sys_get_temp_dir(), 'osm');
    file_put_contents($cfg, json_encode(['attributes' => ['type' => false, 'id' => false], 'linear_tags' => $linien,
        'area_tags' => !$linien, 'exclude_tags' => [], 'include_tags' => $tags]));
    $p = popen('osmium export ' . escapeshellarg($datei) . ' -f geojsonseq -x print_record_separator=false -c '
        . escapeshellarg($cfg) . ' -o - --no-progress 2>/dev/null', 'r');
    $n = 0;
    while (($z = fgets($p)) !== false) {
        $j = json_decode($z, true);
        if (isset($j['geometry']['type'])) {
            $f($j['geometry'], $j['properties'] ?? []);
            $n++;
        }
    }
    $rc = pclose($p);
    unlink($cfg);
    if ($rc !== 0) {
        fwrite(STDERR, "osmium export $datei fehlgeschlagen ($rc).\n");
        exit(1);
    }
    return $n;
}

/** Ein Punkt auf/in der Geometrie: Punkt, Mitte des mittleren Linienstücks, Mittel der Außenring-Ecken. */
function punkt(array $g): ?array
{
    $c = $g['coordinates'];
    switch ($g['type']) {
        case 'Point':
            return [$c[1], $c[0]];
        case 'LineString':
            $n = count($c);
            $a = $c[intdiv($n - 1, 2)];
            $b = $c[intdiv($n, 2)];
            return [($a[1] + $b[1]) / 2, ($a[0] + $b[0]) / 2];
        case 'MultiLineString':
            return punkt(['type' => 'LineString', 'coordinates' => $c[0]]);
        case 'Polygon':
        case 'MultiPolygon':
            $ring = $g['type'] === 'Polygon' ? $c[0] : $c[0][0];
            array_pop($ring);
            $n = max(1, count($ring));
            return [array_sum(array_column($ring, 1)) / $n, array_sum(array_column($ring, 0)) / $n];
    }
    return null;
}

function zelle(float $lat, float $lon): array
{
    return [(int) floor($lat / ZELLE_LAT), (int) floor($lon / ZELLE_LON)];
}

/** norm() mit Zwischenspeicher — dieselben Straßen- und Ortsnamen kommen millionenfach vor. */
function n(string $s): string
{
    static $memo = [];
    return $memo[$s] ??= StrassenSuche::norm($s);
}

function ersterWert(?string $v): string
{
    return trim(explode(';', (string) $v)[0]);
}

function haeufigster(array $zaehler): string
{
    arsort($zaehler);
    return (string) array_key_first($zaehler);
}

/** Erstes Wort des Orts: "Freiburg im Breisgau" und "Freiburg" gelten in derselben Gemeinde als ein Ort. */
function ortKern(string $ort): string
{
    return n(preg_split('/[\s,.\/(-]+/u', $ort, 2, PREG_SPLIT_NO_EMPTY)[0] ?? '');
}

$db = Db::get();

// ---------------------------------------------------------------- 1. PLZ-Gebiete in eine temporäre Hilfstabelle
$db->exec('DROP TEMPORARY TABLE IF EXISTS strassen_plz_hilfe');
$db->exec('CREATE TEMPORARY TABLE strassen_plz_hilfe (plz CHAR(5) NOT NULL, geom GEOMETRY NOT NULL, SPATIAL INDEX sp_geom (geom)) ENGINE=Aria');
$insPlz = $db->prepare('INSERT INTO strassen_plz_hilfe (plz, geom) VALUES (?, ST_GeomFromText(?))');
$wkt = function (array $g): string {
    $ring = fn(array $r): string => '(' . implode(',', array_map(fn($p) => $p[0] . ' ' . $p[1], $r)) . ')';
    $poly = fn(array $p): string => '(' . implode(',', array_map($ring, $p)) . ')';
    return $g['type'] === 'Polygon' ? 'POLYGON' . $poly($g['coordinates'])
        : 'MULTIPOLYGON(' . implode(',', array_map($poly, $g['coordinates'])) . ')';
};
$plzFehler = 0;
$plzAnzahl = 0;
objekte("$arbeit/plz.osm.pbf", ['postal_code', 'boundary'], false, function (array $g, array $t) use ($insPlz, $wkt, &$plzFehler, &$plzAnzahl) {
    $plz = ersterWert($t['postal_code'] ?? null);
    if (!preg_match('/^\d{5}$/', $plz) || !in_array($g['type'], ['Polygon', 'MultiPolygon'], true)) {
        return;
    }
    try {
        $insPlz->execute([$plz, $wkt($g)]);
        $plzAnzahl++;
    } catch (PDOException $e) {
        $plzFehler++;
    }
});
$log("$plzAnzahl PLZ-Gebiete geladen" . ($plzFehler ? ", $plzFehler mit ungültiger Geometrie übersprungen" : ''));
$plzAbfrage = $db->prepare('SELECT plz FROM strassen_plz_hilfe WHERE ST_Contains(geom, ST_GeomFromText(?)) LIMIT 1');
$gemeindeAbfrage = $db->prepare('SELECT ags, name FROM gebiete_gemeinde WHERE ST_Contains(geom, ST_GeomFromText(?)) LIMIT 1');
$plzFuer = function (float $lat, float $lon) use ($plzAbfrage): string {
    $plzAbfrage->execute([sprintf('POINT(%.7F %.7F)', $lon, $lat)]);
    return (string) $plzAbfrage->fetchColumn();
};
$gemeindeFuer = function (float $lat, float $lon) use ($gemeindeAbfrage): ?array {
    $gemeindeAbfrage->execute([sprintf('POINT(%.7F %.7F)', $lon, $lat)]);
    return $gemeindeAbfrage->fetch() ?: null;
};

// ---------------------------------------------------------------- 2. Adresspunkte zusammenfassen
// Gruppe: [0 Anzahl, 1 Summe lat, 2 Summe lon, 3 Schreibweisen, 4 Orte, 5 Probe [lat, lon, …]]
$gruppen = [];
$adressen = objekte("$arbeit/adressen.osm.pbf", ['addr:street', 'addr:postcode', 'addr:city'], false,
    function (array $g, array $t) use (&$gruppen) {
        $strasse = ersterWert($t['addr:street'] ?? null);
        $nn = n($strasse);
        if ($nn === '' || ($p = punkt($g)) === null) {
            return;
        }
        $plz = ersterWert($t['addr:postcode'] ?? null);
        $plz = preg_match('/^\d{5}$/', $plz) ? $plz : '';
        $ort = ersterWert($t['addr:city'] ?? null);
        if (preg_match('/\d/', $ort)) {
            $ort = '';   // "Hannover11", "57537 Wissen / Sieg", "Celle+49": dann lieber die Gemeinde
        }
        $key = $nn . "\t" . $plz . "\t" . n($ort);
        if ($plz === '' || $ort === '') {
            $key .= "\t" . implode(':', zelle($p[0], $p[1])); // unvollständig: nur Punkte aus der Nähe zusammenfassen
        }
        $gr = &$gruppen[$key];
        if ($gr === null) {
            $gr = [0, 0.0, 0.0, [], [], []];
        }
        $gr[0]++;
        $gr[1] += $p[0];
        $gr[2] += $p[1];
        $gr[3][$strasse] = ($gr[3][$strasse] ?? 0) + 1;
        if ($ort !== '') {
            $gr[4][$ort] = ($gr[4][$ort] ?? 0) + 1;
        }
        if ($gr[0] <= PROBE) {
            array_push($gr[5], $p[0], $p[1]);
        } elseif (mt_rand(1, $gr[0]) <= PROBE) {
            $i = mt_rand(0, PROBE - 1) * 2;
            $gr[5][$i] = $p[0];
            $gr[5][$i + 1] = $p[1];
        }
    });
$log("$adressen Adresspunkte gelesen, " . count($gruppen) . ' Gruppen Straße+PLZ+Ort');

// ---------------------------------------------------------------- 3. Straßen (Linien) je Name und Zelle
// [0 Anzahl Wege, 1 lat, 2 lon, 3 Stützpunkte des längsten Wegs, 4 Schreibweisen]
$wege = [];
$wegAnzahl = objekte("$arbeit/strassen.osm.pbf", ['name', 'highway'], true, function (array $g, array $t) use (&$wege) {
    $name = trim((string) ($t['name'] ?? ''));
    $nn = n($name);
    if ($nn === '' || !in_array($g['type'], ['LineString', 'MultiLineString'], true) || ($p = punkt($g)) === null) {
        return;
    }
    $key = $nn . "\t" . implode(':', zelle($p[0], $p[1]));
    $laenge = count($g['type'] === 'LineString' ? $g['coordinates'] : $g['coordinates'][0]);
    $w = &$wege[$key];
    if ($w === null) {
        $w = [0, $p[0], $p[1], 0, []];
    }
    $w[0]++;
    if ($laenge > $w[3]) {
        [$w[1], $w[2], $w[3]] = [$p[0], $p[1], $laenge];
    }
    $w[4][$name] = ($w[4][$name] ?? 0) + 1;
});
$log("$wegAnzahl Straßenstücke gelesen, " . count($wege) . ' Name+Zelle');

// Zellen, in denen es Adressen einer Straße gibt (je Name)
$adrZellen = [];
foreach ($gruppen as $key => $gr) {
    $nn = strstr($key, "\t", true);
    for ($i = 0; $i < count($gr[5]); $i += 2) {
        $adrZellen[$nn][implode(':', zelle($gr[5][$i], $gr[5][$i + 1]))] = true;
    }
    $adrZellen[$nn][implode(':', zelle($gr[1] / $gr[0], $gr[2] / $gr[0]))] = true;
}

// ---------------------------------------------------------------- 4. Zeilen bilden
// Schlüssel: Straße + PLZ + Gemeinde + erstes Wort des Orts. So werden Schreibvarianten
// ("Weilheim i.OB" / "Weilheim in Oberbayern") eine Zeile, Nachbardörfer mit gemeinsamer
// PLZ (eigene Gemeinden oder andere Ortsnamen) bleiben getrennte Vorschläge.
$zeilen = [];   // Schlüssel => [name, plz, ort, ags, lat, lon, adressen]
$statistik = ['aus Adressen' => 0, 'nur Straße' => 0, 'PLZ ergänzt' => 0, 'Ort = Gemeinde' => 0, 'außerhalb' => 0,
    'Straßennummer statt Name' => 0, 'ohne PLZ' => 0];
$aufnehmen = function (array $z) use (&$zeilen): void {
    $key = n($z[0]) . "\t" . $z[1] . "\t" . $z[3] . "\t" . ortKern($z[2]);
    if (!isset($zeilen[$key])) {
        $zeilen[$key] = $z;
        return;
    }
    $alt = &$zeilen[$key];
    if ($z[6] > $alt[6]) {           // Schreibweise, Ort und Punkt der größeren Gruppe behalten
        [$alt[0], $alt[2], $alt[4], $alt[5]] = [$z[0], $z[2], $z[4], $z[5]];
    }
    $alt[6] += $z[6];
};

$i = 0;
foreach ($gruppen as $key => $gr) {
    if (++$i % 200000 === 0) {
        $log("Adressgruppen: $i");
    }
    [$mLat, $mLon] = [$gr[1] / $gr[0], $gr[2] / $gr[0]];
    // Kartenpunkt: die Probe-Adresse, die dem Mittelpunkt am nächsten liegt (liegt sicher an der Straße)
    $best = PHP_FLOAT_MAX;
    for ($k = 0; $k < count($gr[5]); $k += 2) {
        $d = ($gr[5][$k] - $mLat) ** 2 + (($gr[5][$k + 1] - $mLon) * 0.63) ** 2;
        if ($d < $best) {
            [$best, $lat, $lon] = [$d, $gr[5][$k], $gr[5][$k + 1]];
        }
    }
    $gemeinde = $gemeindeFuer($lat, $lon);
    if ($gemeinde === null) {
        $statistik['außerhalb']++;
        continue;
    }
    $plz = explode("\t", $key)[1];
    if ($plz === '' && ($plz = $plzFuer($lat, $lon)) !== '') {
        $statistik['PLZ ergänzt']++;
    }
    $ort = $gr[4] !== [] ? haeufigster($gr[4]) : $gemeinde['name'];
    if ($gr[4] === []) {
        $statistik['Ort = Gemeinde']++;
    }
    $statistik['aus Adressen']++;
    $aufnehmen([haeufigster($gr[3]), $plz, $ort, $gemeinde['ags'], $lat, $lon, $gr[0]]);
}
unset($gruppen);
$log('Adressgruppen fertig');

// Straßen ohne Adressen: zusammenhängende Zellen gleichen Namens bilden ein Stück
$jeName = [];
foreach ($wege as $key => $w) {
    [$nn, $z] = explode("\t", $key);
    $jeName[$nn][$z] = $w;
}
unset($wege);
foreach ($jeName as $nn => $zellen) {
    $offen = $zellen;
    while ($offen !== []) {
        $erste = array_key_first($offen);
        $stueck = [$erste => $offen[$erste]];
        unset($offen[$erste]);
        $warteschlange = [$erste];
        while ($warteschlange !== []) {
            [$y, $x] = array_map('intval', explode(':', array_pop($warteschlange)));
            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    $nb = ($y + $dy) . ':' . ($x + $dx);
                    if (isset($offen[$nb])) {
                        $stueck[$nb] = $offen[$nb];
                        unset($offen[$nb]);
                        $warteschlange[] = $nb;
                    }
                }
            }
        }
        // Gibt es Adressen dieses Namens in einer der Zellen oder direkt daneben? Dann ist die Straße schon drin.
        $abgedeckt = false;
        foreach (array_keys($stueck) as $z) {
            [$y, $x] = array_map('intval', explode(':', $z));
            for ($dy = -1; $dy <= 1 && !$abgedeckt; $dy++) {
                for ($dx = -1; $dx <= 1 && !$abgedeckt; $dx++) {
                    $abgedeckt = isset($adrZellen[$nn][($y + $dy) . ':' . ($x + $dx)]);
                }
            }
            if ($abgedeckt) {
                break;
            }
        }
        if ($abgedeckt) {
            continue;
        }
        usort($stueck, fn($a, $b) => $b[3] <=> $a[3]);
        [, $lat, $lon] = $stueck[0];
        $namen = [];
        foreach ($stueck as $w) {
            foreach ($w[4] as $name => $c) {
                $namen[$name] = ($namen[$name] ?? 0) + $c;
            }
        }
        $name = haeufigster($namen);
        if (preg_match('/^([A-Z]{1,2} ?\d+[a-z]?|\d+)$/u', $name)) {
            $statistik['Straßennummer statt Name']++;   // "A9", "B 96", "18" — ohne Adressen kein echter Straßenname
            continue;
        }
        $gemeinde = $gemeindeFuer($lat, $lon);
        if ($gemeinde === null) {
            $statistik['außerhalb']++;
            continue;
        }
        $statistik['nur Straße']++;
        $aufnehmen([$name, $plzFuer($lat, $lon), $gemeinde['name'], $gemeinde['ags'], $lat, $lon, 0]);
    }
}
unset($jeName, $adrZellen);
$db->exec('DROP TEMPORARY TABLE IF EXISTS strassen_plz_hilfe');

foreach ($zeilen as $z) {
    if ($z[1] === '') {
        $statistik['ohne PLZ']++;
    }
}
$log(count($zeilen) . ' Zeilen. ' . implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($statistik), $statistik)));
$beispiele = array_filter($zeilen, fn($z) => str_starts_with($z[0], 'Kantstraße') && $z[2] === 'Berlin');
foreach (array_slice($beispiele, 0, 5) as $z) {
    $log('  Beispiel: ' . implode(' | ', $z));
}

if (isset($opt['export'])) {
    $fh = fopen('compress.zlib://' . $opt['export'], 'w');
    fputcsv($fh, ['stand|' . $osmStand . '|' . basename($pbf), 'plz', 'ort', 'gemeinde_ags', 'lat', 'lon', 'adressen'], ';', '"', '');
    foreach ($zeilen as $z) {
        fputcsv($fh, [$z[0], $z[1], $z[2], $z[3], round($z[4], 5), round($z[5], 5), $z[6]], ';', '"', '');
    }
    fclose($fh);
    $log('Ergebnis exportiert: ' . $opt['export'] . ' (' . round(filesize($opt['export']) / 1048576, 1) . ' MB)');
}
if ($dryRun) {
    $log('Testlauf: nichts in die DB geschrieben.');
    exit(0);
}
$n = speichern($zeilen, $osmStand, basename($pbf));
$groesse = $db->query("SELECT ROUND((data_length + index_length) / 1048576) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'strassen'")->fetchColumn();
$log("$n Straßen gespeichert, Tabelle $groesse MB");
