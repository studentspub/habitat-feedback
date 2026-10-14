<?php
/**
 * ÖPNV-Takt je Haltestelle aus dem GTFS-Feed Deutschland (Issue #24): Abfahrten
 * an einem normalen Werktag, je Station zusammengefasst, mit Verkehrsmitteln.
 * Macht aus "Haltestelle in der Nähe" eine echte Anbindungsqualität.
 *
 * Quelle: https://gtfs.de/de/feeds/de_full/ (Download https://download.gtfs.de/germany/free/latest.zip,
 * ~280 MB, Datengrundlage DELFI e.V.), Creative Commons 4.0. Der Feed gilt jeweils
 * für die nächsten 30 Tage.
 *
 * Stichtag: Ohne --stichtag nimmt das Skript unter allen Dienstagen bis
 * Donnerstagen im Gültigkeitszeitraum den Tag mit den meisten Fahrten —
 * so fallen Feiertage und (in den meisten Ländern) Schulferien von selbst
 * heraus, ohne Ferienliste.
 *
 * Zusammenfassen: Masten/Bahnsteige mit gemeinsamer parent_station = eine
 * Station; gleichnamige Masten ohne Station in derselben ~200-m-Zelle
 * (z.B. beide Fahrtrichtungen) ebenfalls.
 *
 * Die Auswertung braucht `unzip` und ~1-2 GB RAM (WSL). Der Container
 * importiert stattdessen das Ergebnis (--export in WSL, --ergebnis im Container).
 *
 * Voraussetzung: Migration sql/024_oepnv.sql
 *
 * Aufruf: php bin/import_gtfs.php [--zip=DATEI] [--stichtag=JJJJ-MM-TT] [--export=DATEI.csv.gz] [--dry-run]
 *         php bin/import_gtfs.php --ergebnis=DATEI.csv.gz     (nur das fertige Ergebnis einspielen)
 *   --zip       GTFS-Zip (Standard: neueste *.zip in /srv/daten/gtfs)
 *   --stichtag  Werktag fest vorgeben statt automatisch
 *   --export    Ergebnis zusätzlich als .csv.gz schreiben (für den Container)
 *   --ergebnis  statt GTFS-Auswertung ein mit --export geschriebenes Ergebnis importieren
 *   --dry-run   auswerten und zählen, nichts in die DB schreiben
 *   --help      nur diese Hilfe
 */

ini_set('memory_limit', '3G');
require __DIR__ . '/../app/Support/Db.php';

use App\Support\Db;

const MINDEST_STATIONEN = 50000;

$opt = getopt('', ['zip:', 'stichtag:', 'export:', 'ergebnis:', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$dryRun = isset($opt['dry-run']);
$start = microtime(true);
$log = fn(string $t) => printf("[%4.0f s] %s\n", microtime(true) - $start, $t);

/** Schreibt Stationen in die DB (Transaktion, Rollback bei zu wenigen). */
function speichern(array $stationen, string $stichtag, string $quelle): void
{
    if (count($stationen) < MINDEST_STATIONEN) {
        fwrite(STDERR, 'Nur ' . count($stationen) . ' Stationen — abgebrochen, alte Daten bleiben.' . "\n");
        exit(1);
    }
    $db = Db::get();
    $db->beginTransaction();
    $db->exec('DELETE FROM oepnv_haltestellen');
    $puffer = [];
    $flush = function () use (&$puffer, $db): void {
        if ($puffer === []) {
            return;
        }
        $db->prepare('INSERT INTO oepnv_haltestellen (id, name, lat, lon, pt, abfahrten, abfahrten_6_20, verkehrsmittel) VALUES '
            . implode(',', array_fill(0, count($puffer), '(?,?,?,?,ST_GeomFromText(?),?,?,?)')))->execute(array_merge(...$puffer));
        $puffer = [];
    };
    foreach ($stationen as $s) {
        $puffer[] = [$s['id'], mb_substr($s['name'], 0, 200), $s['lat'], $s['lon'], sprintf('POINT(%.7F %.7F)', $s['lon'], $s['lat']),
            $s['abfahrten'], $s['abfahrten_6_20'], $s['verkehrsmittel']];
        if (count($puffer) >= 1000) {
            $flush();
        }
    }
    $flush();
    $db->exec('DELETE FROM oepnv_stand');
    $db->prepare('INSERT INTO oepnv_stand (id, stichtag, feed_datei) VALUES (1, ?, ?)')->execute([$stichtag, basename($quelle)]);
    $db->commit();
}

// ---------------------------------------------------------------- nur Ergebnis einspielen (Container)
if (isset($opt['ergebnis'])) {
    $fh = gzopen($opt['ergebnis'], 'r');
    $kopf = str_getcsv(trim(gzgets($fh)), ';', '"', '');
    [, $stichtag, $feed] = explode('|', array_shift($kopf));
    $stationen = [];
    while (($z = gzgets($fh)) !== false) {
        $s = str_getcsv(trim($z), ';', '"', '');
        $stationen[] = ['id' => $s[0], 'name' => $s[1], 'lat' => (float) $s[2], 'lon' => (float) $s[3],
            'abfahrten' => (int) $s[4], 'abfahrten_6_20' => (int) $s[5], 'verkehrsmittel' => $s[6]];
    }
    gzclose($fh);
    $log(count($stationen) . " Stationen aus dem Ergebnis gelesen (Stichtag $stichtag)");
    if (!$dryRun) {
        speichern($stationen, $stichtag, $feed);
        $log('gespeichert');
    }
    exit(0);
}

// ---------------------------------------------------------------- GTFS auswerten
$zip = $opt['zip'] ?? null;
if ($zip === null) {
    $kandidaten = glob('/srv/daten/gtfs/*.zip') ?: [];
    sort($kandidaten);
    $zip = end($kandidaten) ?: null;
}
if ($zip === null || !is_file($zip)) {
    fwrite(STDERR, "GTFS-Zip nicht gefunden (--zip angeben).\n");
    exit(1);
}
if (trim((string) shell_exec('command -v unzip')) === '') {
    fwrite(STDERR, "unzip fehlt — die GTFS-Auswertung läuft in WSL, im Container --ergebnis verwenden.\n");
    exit(1);
}

/** Liest eine GTFS-Datei zeilenweise; ruft $f(array $spalte => wert) je Zeile. */
function gtfsLesen(string $zip, string $datei, callable $f): void
{
    $fh = popen('unzip -p ' . escapeshellarg($zip) . ' ' . escapeshellarg($datei), 'r');
    $kopf = fgetcsv($fh, 0, ',', '"', '');
    if ($kopf === false) {
        pclose($fh);
        return;
    }
    $kopf[0] = preg_replace('/^\xEF\xBB\xBF/', '', $kopf[0]);
    $n = count($kopf);
    while (($z = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if (count($z) === $n) {
            $f(array_combine($kopf, $z));
        }
    }
    pclose($fh);
}

// Verkehrsmittel aus route_type (GTFS-Basis- und erweiterte Typen)
function verkehrsmittel(int $t): string
{
    return match (true) {
        $t === 0 || ($t >= 900 && $t < 1000) => 'Tram',
        $t === 1 || ($t >= 400 && $t < 500) => 'U-Bahn',
        $t === 109 => 'S-Bahn',
        $t === 2 || ($t >= 100 && $t < 200) => 'Zug',
        $t === 3 || ($t >= 700 && $t < 800) || $t === 200 => 'Bus',
        $t === 4 || ($t >= 1000 && $t < 1300) => 'Fähre',
        default => 'Sonstige',
    };
}

// 1) Kalender: je service_id Wochentage + Zeitraum, Ausnahmen je Datum
$kalender = [];
gtfsLesen($zip, 'calendar.txt', function ($r) use (&$kalender) {
    $kalender[$r['service_id']] = [$r['start_date'], $r['end_date'],
        [1 => $r['monday'], $r['tuesday'], $r['wednesday'], $r['thursday'], $r['friday'], $r['saturday'], $r['sunday']]];
});
$ausnahmen = []; // datum => service_id => 1 (dazu) | 2 (weg)
gtfsLesen($zip, 'calendar_dates.txt', function ($r) use (&$ausnahmen) {
    $ausnahmen[$r['date']][$r['service_id']] = (int) $r['exception_type'];
});
$log(count($kalender) . ' Kalender, Ausnahmen an ' . count($ausnahmen) . ' Tagen');

$aktivAm = function (string $datum) use ($kalender, $ausnahmen): array {
    $wt = (int) date('N', strtotime($datum));
    $aktiv = [];
    foreach ($kalender as $sid => [$von, $bis, $tage]) {
        if ($datum >= $von && $datum <= $bis && $tage[$wt] === '1') {
            $aktiv[$sid] = true;
        }
    }
    foreach ($ausnahmen[$datum] ?? [] as $sid => $typ) {
        if ($typ === 1) {
            $aktiv[$sid] = true;
        } else {
            unset($aktiv[$sid]);
        }
    }
    return $aktiv;
};

// 2) Fahrten je service_id zählen (für die Stichtagswahl), Linie je Fahrt
$routenTyp = [];
gtfsLesen($zip, 'routes.txt', function ($r) use (&$routenTyp) {
    $routenTyp[$r['route_id']] = (int) $r['route_type'];
});
$fahrtenJeService = [];
$fahrtInfo = []; // trip_id => [service_id, route_type]
gtfsLesen($zip, 'trips.txt', function ($r) use (&$fahrtenJeService, &$fahrtInfo, $routenTyp) {
    $fahrtenJeService[$r['service_id']] = ($fahrtenJeService[$r['service_id']] ?? 0) + 1;
    $fahrtInfo[$r['trip_id']] = [$r['service_id'], $routenTyp[$r['route_id']] ?? 3];
});
$log(count($fahrtInfo) . ' Fahrten, ' . count($routenTyp) . ' Linien');

// 3) Stichtag: vorgegeben oder Di-Do mit den meisten Fahrten im Gültigkeitszeitraum
$daten = array_merge(array_column($kalender, 0), array_column($kalender, 1), array_keys($ausnahmen));
$min = max(min($daten), date('Ymd'));
$max = max($daten);
if (isset($opt['stichtag'])) {
    $stichtag = str_replace('-', '', $opt['stichtag']);
} else {
    $best = null;
    for ($t = strtotime($min); date('Ymd', $t) <= $max; $t += 86400) {
        if (!in_array((int) date('N', $t), [2, 3, 4], true)) {
            continue;
        }
        $d = date('Ymd', $t);
        $summe = 0;
        foreach ($aktivAm($d) as $sid => $_) {
            $summe += $fahrtenJeService[$sid] ?? 0;
        }
        $log("  Kandidat $d: $summe Fahrten");
        if ($best === null || $summe > $best[1]) {
            $best = [$d, $summe];
        }
    }
    $stichtag = $best[0];
}
$aktiv = $aktivAm($stichtag);
$aktiveFahrten = [];
foreach ($fahrtInfo as $tid => [$sid, $typ]) {
    if (isset($aktiv[$sid])) {
        $aktiveFahrten[$tid] = $typ;
    }
}
unset($fahrtInfo);
$stichtagIso = substr($stichtag, 0, 4) . '-' . substr($stichtag, 4, 2) . '-' . substr($stichtag, 6, 2);
$log("Stichtag $stichtagIso: " . count($aktiveFahrten) . ' Fahrten');

// 4) Haltestellen
$stops = [];
gtfsLesen($zip, 'stops.txt', function ($r) use (&$stops) {
    $stops[$r['stop_id']] = [$r['stop_name'], (float) $r['stop_lat'], (float) $r['stop_lon'], $r['parent_station'] ?? '', (int) ($r['location_type'] ?? 0)];
});
$log(count($stops) . ' Haltestellen/Masten');

// 5) Abfahrten zählen (stop_times ist die große Datei, ~40 Mio. Zeilen)
$abf = [];
$abfTag = [];
$mittel = [];
$zeilen = 0;
gtfsLesen($zip, 'stop_times.txt', function ($r) use (&$abf, &$abfTag, &$mittel, &$zeilen, $aktiveFahrten, $log) {
    if (++$zeilen % 10000000 === 0) {
        $log('  … ' . ($zeilen / 1e6) . ' Mio. Halte gelesen');
    }
    $typ = $aktiveFahrten[$r['trip_id']] ?? null;
    if ($typ === null || ($r['pickup_type'] ?? '0') === '1') {
        return; // nicht am Stichtag oder kein Einstieg (meist Endhalt)
    }
    $sid = $r['stop_id'];
    $abf[$sid] = ($abf[$sid] ?? 0) + 1;
    $stunde = (int) substr($r['departure_time'], 0, 2) % 24;
    if ($stunde >= 6 && $stunde < 20) {
        $abfTag[$sid] = ($abfTag[$sid] ?? 0) + 1;
    }
    $mittel[$sid][verkehrsmittel($typ)] = true;
});
$log("$zeilen Halte gelesen, Abfahrten an " . count($abf) . ' Masten');

// 6) Zusammenfassen zu Stationen
$stationen = [];
foreach ($abf as $sid => $anzahl) {
    if (!isset($stops[$sid])) {
        continue;
    }
    [$name, $lat, $lon, $parent] = $stops[$sid];
    if ($parent !== '' && isset($stops[$parent])) {
        $key = 'P:' . $parent;
        [$name, $plat, $plon] = $stops[$parent];
        if ($plat != 0.0) {
            [$lat, $lon] = [$plat, $plon];
        }
    } else {
        $key = 'N:' . mb_strtolower($name) . ':' . round($lat / 0.002) . ':' . round($lon / 0.003);
    }
    if (!isset($stationen[$key])) {
        $stationen[$key] = ['id' => substr(md5($key), 0, 16), 'name' => $name, 'latSum' => 0, 'lonSum' => 0, 'n' => 0,
            'abfahrten' => 0, 'abfahrten_6_20' => 0, 'mittel' => []];
    }
    $s = &$stationen[$key];
    $s['latSum'] += $lat;
    $s['lonSum'] += $lon;
    $s['n']++;
    $s['abfahrten'] += $anzahl;
    $s['abfahrten_6_20'] += $abfTag[$sid] ?? 0;
    $s['mittel'] += $mittel[$sid] ?? [];
    unset($s);
}
$reihenfolge = ['Zug', 'S-Bahn', 'U-Bahn', 'Tram', 'Bus', 'Fähre', 'Sonstige'];
foreach ($stationen as &$s) {
    $s['lat'] = $s['latSum'] / $s['n'];
    $s['lon'] = $s['lonSum'] / $s['n'];
    $s['verkehrsmittel'] = implode(', ', array_values(array_intersect($reihenfolge, array_keys($s['mittel']))));
}
unset($s);
$stationen = array_values($stationen);
usort($stationen, fn($a, $b) => $b['abfahrten'] <=> $a['abfahrten']);
$log(count($stationen) . ' Stationen. Meiste Abfahrten: '
    . implode('; ', array_map(fn($s) => $s['name'] . ' ' . $s['abfahrten'], array_slice($stationen, 0, 5))));

if (isset($opt['export'])) {
    $fh = gzopen($opt['export'], 'w6');
    gzwrite($fh, 'stand|' . $stichtagIso . '|' . basename($zip) . ";name;lat;lon;abfahrten;abfahrten_6_20;verkehrsmittel\n");
    foreach ($stationen as $s) {
        $felder = [$s['id'], $s['name'], round($s['lat'], 6), round($s['lon'], 6), $s['abfahrten'], $s['abfahrten_6_20'], $s['verkehrsmittel']];
        gzwrite($fh, implode(';', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $felder)) . "\n");
    }
    gzclose($fh);
    $log('Ergebnis exportiert: ' . $opt['export']);
}
if ($dryRun) {
    $log('Testlauf: nichts in die DB geschrieben.');
    exit(0);
}
speichern($stationen, $stichtagIso, $zip);
$log('gespeichert');
