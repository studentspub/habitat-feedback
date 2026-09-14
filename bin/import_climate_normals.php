<?php
/**
 * Einmaliger Import der DWD-Klimanormalwerte (1991-2020) in climate_normals.
 * Läuft nur bei Bedarf per Hand, nicht bei jedem Request — Referenzdaten
 * ändern sich nur mit einem neuen Bezugszeitraum (nächster fällig: 2031).
 *
 * Aufruf: php bin/import_climate_normals.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/Net.php';

use App\Support\Db;

const BASE = 'https://opendata.dwd.de/climate_environment/CDC/observations_germany/climate/multi_annual/mean_91-20/';
const MONTH_KEYS = ['jan', 'feb', 'mar', 'apr', 'mai', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dez'];

function fetchDwd(string $path): string
{
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: habitat-feedback-import/1.0\r\n", 'timeout' => 30]]);
    $raw = file_get_contents(BASE . $path, false, $ctx);
    if ($raw === false) {
        fwrite(STDERR, "Fehler beim Laden von $path\n");
        exit(1);
    }
    // DWD liefert die Dateien in ISO-8859-1 (Umlaute in Stationsnamen).
    return mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
}

/** @return array<int, array{name:string, lat:float, lon:float}> */
function parseStations(string $content): array
{
    $stations = [];
    $lines = explode("\n", trim($content));
    array_shift($lines); // Kopfzeile
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(';', $line));
        if (count($cols) < 4 || $cols[0] === '') {
            continue;
        }
        $stations[(int) $cols[0]] = ['name' => $cols[1], 'lat' => (float) $cols[2], 'lon' => (float) $cols[3]];
    }
    return $stations;
}

/** @return array<int, array<string, float|null>> Werte je Station: 12 Monate + 'jahr' */
function parseValues(string $content): array
{
    $values = [];
    $lines = explode("\n", trim($content));
    array_shift($lines); // Kopfzeile
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(';', $line));
        if (count($cols) < 16 || $cols[0] === '') {
            continue;
        }
        $stationId = (int) $cols[0];
        $monthValues = array_slice($cols, 3, 12); // nach Stations_id;Bezugszeitraum;Datenquelle
        $row = [];
        foreach (MONTH_KEYS as $i => $key) {
            $row[$key] = $monthValues[$i] === '' ? null : (float) $monthValues[$i];
        }
        $row['jahr'] = $cols[15] === '' ? null : (float) $cols[15];
        $values[$stationId] = $row;
    }
    return $values;
}

echo "Lade Temperatur-Daten...\n";
$tempStations = parseStations(fetchDwd('Temperatur_1991-2020_Stationsliste.txt'));
$tempValues = parseValues(fetchDwd('Temperatur_1991-2020.txt'));

echo "Lade Niederschlag-Daten...\n";
$precipStations = parseStations(fetchDwd('Niederschlag_1991-2020_Stationsliste.txt'));
$precipValues = parseValues(fetchDwd('Niederschlag_1991-2020.txt'));

$allStationIds = array_unique(array_merge(array_keys($tempStations), array_keys($precipStations)));
echo count($allStationIds) . " Stationen insgesamt (Temperatur: " . count($tempStations) . ", Niederschlag: " . count($precipStations) . ")\n";

$db = Db::get();
$columns = ['station_id', 'station_name', 'lat', 'lon', 'period'];
foreach (MONTH_KEYS as $m) { $columns[] = "temp_$m"; }
$columns[] = 'temp_jahr';
foreach (MONTH_KEYS as $m) { $columns[] = "precip_$m"; }
$columns[] = 'precip_jahr';

$placeholders = ':' . implode(', :', $columns);
$updateClause = implode(', ', array_map(fn ($c) => "$c = VALUES($c)", array_slice($columns, 1)));
$stmt = $db->prepare(
    'INSERT INTO climate_normals (' . implode(', ', $columns) . ") VALUES ($placeholders)
     ON DUPLICATE KEY UPDATE $updateClause"
);

$imported = 0;
foreach ($allStationIds as $id) {
    $station = $tempStations[$id] ?? $precipStations[$id] ?? null;
    if ($station === null) {
        continue;
    }
    $t = $tempValues[$id] ?? array_fill_keys(array_merge(MONTH_KEYS, ['jahr']), null);
    $p = $precipValues[$id] ?? array_fill_keys(array_merge(MONTH_KEYS, ['jahr']), null);

    $params = ['station_id' => $id, 'station_name' => $station['name'], 'lat' => $station['lat'], 'lon' => $station['lon'], 'period' => '1991-2020'];
    foreach (MONTH_KEYS as $m) { $params["temp_$m"] = $t[$m]; }
    $params['temp_jahr'] = $t['jahr'];
    foreach (MONTH_KEYS as $m) { $params["precip_$m"] = $p[$m]; }
    $params['precip_jahr'] = $p['jahr'];

    $stmt->execute($params);
    $imported++;
}

echo "$imported Stationen importiert.\n";
