<?php
/**
 * Import der Bushaltestellen (highway=bus_stop) aus einem lokalen
 * Geofabrik-Deutschland-Extrakt in poi_locations (Kategorie 'transit').
 *
 * Ergänzt bin/import_poi.php: dort scheitert highway=bus_stop bundesweit UND
 * je Bundesland zuverlässig an Overpass' eigenen Timeouts/Rate-Limits (mit
 * Abstand die häufigste ÖPNV-Haltestellenart in OSM, entsprechend teuer als
 * Abfrage) — Tram-Haltestellen und Bahnhöfe laufen über import_poi.php
 * weiterhin normal über Overpass, nur Bushaltestellen brauchen diesen Weg.
 *
 * Voraussetzung: osmium-tool installiert (apt install osmium-tool) und ein
 * Deutschland-Extrakt von Geofabrik heruntergeladen:
 *   curl -L -o germany-latest.osm.pbf https://download.geofabrik.de/europe/germany-latest.osm.pbf
 * (ca. 4,8 GB — kein Overpass-Query, daher kein Timeout-/Rate-Limit-Risiko.)
 *
 * Verarbeitung lokal über osmium (kein PHP-PBF-Parser nötig, siehe
 * SimpleXlsxReader-Kommentar zum selben Prinzip bei XLSX): erst mit
 * `tags-filter` auf highway=bus_stop-Knoten reduzieren, dann nach GeoJSON
 * exportieren, das PHP mit json_decode ohne Zusatzbibliothek lesen kann.
 *
 * Aufruf: php bin/import_bus_stops.php [Pfad-zum-PBF-Extrakt]
 *   Ohne Argument wird PBF_PATH (siehe unten) verwendet.
 */

require __DIR__ . '/../app/Support/Db.php';

use App\Support\Db;

const PBF_PATH = '/tmp/germany-latest.osm.pbf';
const FILTERED_PBF = '/tmp/bus_stops.osm.pbf';
const GEOJSON_PATH = '/tmp/bus_stops.geojson';

function runOsmium(string $command): void
{
    echo "  \$ $command\n";
    exec($command . ' 2>&1', $output, $exitCode);
    foreach ($output as $line) {
        echo "    $line\n";
    }
    if ($exitCode !== 0) {
        throw new RuntimeException("osmium-Befehl fehlgeschlagen (Exit $exitCode): $command");
    }
}

$pbfPath = $argv[1] ?? PBF_PATH;
if (!is_file($pbfPath)) {
    fwrite(STDERR, "PBF-Datei nicht gefunden: $pbfPath\n");
    fwrite(STDERR, "Download: curl -L -o $pbfPath https://download.geofabrik.de/europe/germany-latest.osm.pbf\n");
    exit(1);
}

echo "Filtere highway=bus_stop-Knoten aus $pbfPath...\n";
runOsmium('osmium tags-filter ' . escapeshellarg($pbfPath) . ' n/highway=bus_stop -o ' . escapeshellarg(FILTERED_PBF) . ' --overwrite');

echo "Exportiere nach GeoJSON...\n";
// -u type_id fügt jedem Feature ein "@id"-Feld wie "n123456" hinzu (Typ +
// OSM-ID) — ohne dieses Flag liefert osmium nur die Tags, keine ID.
runOsmium('osmium export -u type_id ' . escapeshellarg(FILTERED_PBF) . ' -o ' . escapeshellarg(GEOJSON_PATH) . ' --overwrite -f geojson');

echo 'Lese ' . round(filesize(GEOJSON_PATH) / 1024 / 1024, 1) . " MB GeoJSON ein...\n";
$geojson = json_decode(file_get_contents(GEOJSON_PATH), true);
if (!is_array($geojson) || !isset($geojson['features'])) {
    fwrite(STDERR, "GeoJSON konnte nicht gelesen werden oder hat kein 'features'-Feld.\n");
    exit(1);
}

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO poi_locations (category, osm_id, osm_type, lat, lon, name)
     VALUES (\'transit\', :osm_id, \'node\', :lat, :lon, :name)
     ON DUPLICATE KEY UPDATE lat = VALUES(lat), lon = VALUES(lon), name = VALUES(name), imported_at = NOW()'
);

// Mit autocommit (PDO-Standard) ist jedes einzelne INSERT eine eigene
// Transaktion mit eigenem fsync — bei ~400.000 Zeilen dauert das mehrere
// Stunden statt Sekunden. Eine Transaktion je 5000 Zeilen bringt das auf ein
// erträgliches Maß, ohne eine einzige riesige Transaktion offen zu halten.
const BATCH_SIZE = 5000;

$imported = 0;
$db->beginTransaction();
foreach ($geojson['features'] as $feature) {
    $coords = $feature['geometry']['coordinates'] ?? null;
    $osmId = $feature['id'] ?? null; // dank -u type_id: "n<id>" auf Feature-Ebene, nicht in properties
    if ($coords === null || $osmId === null || !str_starts_with($osmId, 'n')) {
        continue;
    }

    $name = $feature['properties']['name'] ?? null;
    if ($name !== null && mb_strlen($name) > 200) {
        $name = mb_substr($name, 0, 200);
    }

    $upsert->execute([
        'osm_id' => (int) substr($osmId, 1),
        'lon' => (float) $coords[0],
        'lat' => (float) $coords[1],
        'name' => $name,
    ]);
    $imported++;

    if ($imported % BATCH_SIZE === 0) {
        $db->commit();
        $db->beginTransaction();
        echo "  $imported ...\n";
    }
}
$db->commit();

echo "$imported Bushaltestellen importiert.\n";
