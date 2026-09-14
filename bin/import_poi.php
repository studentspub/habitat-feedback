<?php
/**
 * Einmaliger (bzw. periodisch wiederholbarer) Import aller 8 POI-Kategorien
 * für ganz Deutschland in poi_locations. Ersetzt den früheren Live-Ansatz
 * über Overpass bei jedem Kartenschwenk — dieselbe Überlegung wie bei
 * climate_normals/air_stations: einmal ziehen, aus der eigenen DB bedienen.
 *
 * Läuft bewusst mit langem Timeout, weil es ein Hintergrund-Skript ist,
 * kein Live-Request: für ganz Deutschland kann eine Kategorie durchaus
 * mehrere Minuten dauern.
 *
 * Aufruf: php bin/import_poi.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/PoiCategories.php';

use App\Support\Db;
use App\Support\PoiCategories;

const GERMANY_BBOX = '47.27,5.87,55.06,15.04'; // minLat,minLon,maxLat,maxLon
const MAX_ATTEMPTS = 4;

/**
 * Fragt eine Kategorie für ganz Deutschland bei Overpass ab, mit
 * Wiederholungsversuchen bei vorübergehenden Fehlern.
 *
 * Ursprünglich mit area["ISO3166-1"="DE"] geplant (exakte Staatsgrenze statt
 * Rechteck) — beim Testen stellte sich heraus, dass allein das Auflösen
 * dieser Grenze als Polygon so teuer ist, dass es selbst bei nur 3
 * gewünschten Treffern in einen Timeout lief. Eine simple Bounding-Box ist
 * für Overpass drastisch billiger; die Ungenauigkeit (ein schmaler Streifen
 * jenseits der Grenze zu Nachbarländern wird mit erfasst) ist für eine
 * "was gibt's in der Nähe"-Funktion vernachlässigbar.
 */
function fetchCategoryFromOverpass(array $tagFilters): ?array
{
    $clauses = implode('', array_map(
        fn (string $t) => 'node[' . $t . '](' . GERMANY_BBOX . ');way[' . $t . '](' . GERMANY_BBOX . ');',
        $tagFilters
    ));
    $query = '[out:json][timeout:180];(' . $clauses . ');out center;';

    for ($attempt = 1; $attempt <= MAX_ATTEMPTS; $attempt++) {
        $ch = curl_init('https://overpass-api.de/api/interpreter');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            // Als String, nicht als Array — ein Array lässt curl automatisch
            // auf multipart/form-data umschalten, was Overpass nicht
            // erwartet (führt zu HTTP 400 mit einer Fehlermeldung, die nach
            // MIME-Boundary-Resten aussieht, nicht nach einem Query-Fehler).
            CURLOPT_POSTFIELDS => http_build_query(['data' => $query]),
            CURLOPT_HTTPHEADER => ['User-Agent: habitat-feedback-import/1.0 (dev)'],
            CURLOPT_TIMEOUT => 200,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = ($response !== false && $httpCode === 200) ? json_decode($response, true) : null;
        // Overpass antwortet bei einem serverseitigen Timeout manchmal mit
        // HTTP 200 und einem 'remark'-Feld statt eines echten Fehlerstatus
        // — ein leeres elements-Array davon zu unterscheiden ist wichtig,
        // sonst würde ein überlasteter Server stillschweigend als "0 Treffer
        // in dieser Kategorie" missverstanden (derselbe Bug, den wir schon
        // beim Live-Ansatz in PoiController hatten).
        if (is_array($data) && isset($data['elements']) && !isset($data['remark'])) {
            return $data['elements'];
        }

        $wait = $attempt * 15;
        fwrite(STDERR, "  Versuch $attempt/" . MAX_ATTEMPTS . " fehlgeschlagen (HTTP $httpCode" . (isset($data['remark']) ? ', ' . $data['remark'] : '') . "), warte {$wait}s...\n");
        if ($attempt < MAX_ATTEMPTS) {
            sleep($wait);
        }
    }

    return null;
}

/**
 * Wandelt die rohen Overpass-Elemente einer Kategorie in Zeilen für
 * poi_locations um. Ein 'node' hat lat/lon direkt am Element, ein 'way'
 * stattdessen im 'center'-Objekt (Flächenschwerpunkt, dank 'out center'
 * in der Abfrage). Elemente ohne brauchbare Koordinate werden übersprungen
 * statt einen Fehler zu werfen — bei zehntausenden Elementen bundesweit
 * ist unvollständiges Tagging der Normalfall, kein Ausnahmefall.
 *
 * @param array $elements Rohe Overpass-Elemente (gemischt node/way)
 * @return array<int, array{osm_id:int, osm_type:string, lat:float, lon:float, name:?string}>
 */
function mapElementsToRows(array $elements): array
{
    $rows = [];
    foreach ($elements as $el) {
        $lat = $el['lat'] ?? $el['center']['lat'] ?? null;
        $lon = $el['lon'] ?? $el['center']['lon'] ?? null;
        if ($lat === null || $lon === null) {
            continue;
        }

        $rows[] = [
            'osm_id' => (int) $el['id'],
            'osm_type' => $el['type'],
            'lat' => (float) $lat,
            'lon' => (float) $lon,
            'name' => $el['tags']['name'] ?? null,
        ];
    }
    return $rows;
}

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO poi_locations (category, osm_id, osm_type, lat, lon, name)
     VALUES (:category, :osm_id, :osm_type, :lat, :lon, :name)
     ON DUPLICATE KEY UPDATE lat = VALUES(lat), lon = VALUES(lon), name = VALUES(name), imported_at = NOW()'
);

foreach (PoiCategories::CATEGORIES as $category => $tagFilters) {
    echo "Lade Kategorie '$category' für ganz Deutschland...\n";
    $elements = fetchCategoryFromOverpass($tagFilters);
    if ($elements === null) {
        fwrite(STDERR, "  Fehlgeschlagen, überspringe '$category'.\n");
        continue;
    }

    $rows = mapElementsToRows($elements);
    foreach ($rows as $row) {
        $upsert->execute([
            'category' => $category,
            'osm_id' => $row['osm_id'],
            'osm_type' => $row['osm_type'],
            'lat' => $row['lat'],
            'lon' => $row['lon'],
            'name' => $row['name'],
        ]);
    }
    echo '  ' . count($rows) . " Standorte importiert.\n";

    sleep(2); // kurze Pause zwischen den Kategorien, aus Rücksicht auf Overpass
}

echo "Fertig.\n";
