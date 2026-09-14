<?php
/**
 * Einmaliger Import der aktiven UBA-Luftmess-Stationen in air_stations.
 * Aufruf: php bin/import_air_stations.php
 */

require __DIR__ . '/../app/Support/Db.php';

use App\Support\Db;

// Das "station active to"-Feld verhielt sich beim Testen zwischen
// Anfragen widersprüchlich (mal 1175, mal 0 von 2400 als "aktiv"
// gemeldet, offenbar je nach Server/Zeitpunkt) — statt uns darauf zu
// verlassen, werden hier einfach alle Stationen mit Koordinaten
// importiert. Ob eine Station gerade wirklich Daten liefert, klärt sich
// zur Laufzeit im AirQualityController mit einem Fallback auf die
// nächstgelegene Station, die tatsächlich antwortet.
$ctx = stream_context_create(['http' => ['header' => "User-Agent: habitat-feedback-import/1.0\r\n", 'timeout' => 30]]);
$today = date('Y-m-d');
$raw = file_get_contents(
    "https://luftdaten.umweltbundesamt.de/api/air-data/v4/stations/json?date_from=$today&date_to=$today&lang=de",
    false,
    $ctx
);
if ($raw === false) {
    fwrite(STDERR, "Fehler beim Laden der Stationsliste.\n");
    exit(1);
}
$data = json_decode($raw, true);

$db = Db::get();
$stmt = $db->prepare(
    'INSERT INTO air_stations (station_id, station_name, city, lat, lon) VALUES (:id, :name, :city, :lat, :lon)
     ON DUPLICATE KEY UPDATE station_name = VALUES(station_name), city = VALUES(city), lat = VALUES(lat), lon = VALUES(lon)'
);

$imported = 0;
foreach ($data['data'] as $id => $row) {
    // Indices: 0 id, 1 code, 2 name, 3 city, 4 synonym, 5 active_from,
    // 6 active_to, 7 lon, 8 lat, ...
    $lon = $row[7];
    $lat = $row[8];
    if ($lon === '' || $lat === '') {
        continue; // ohne Koordinaten nicht brauchbar
    }
    $stmt->execute(['id' => (int) $id, 'name' => $row[2], 'city' => $row[3], 'lat' => (float) $lat, 'lon' => (float) $lon]);
    $imported++;
}

echo "$imported aktive Stationen importiert.\n";
