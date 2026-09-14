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
 * Aufruf: php bin/import_poi.php [kategorie ...]
 *   Ohne Argumente werden alle Kategorien geladen. Mit Argumenten nur die
 *   genannten — nützlich, weil Overpass immer wieder einzelne Kategorien
 *   mit 504 wegwirft: dann muss nicht der gesamte Lauf (mehrere Minuten je
 *   Kategorie) wiederholt werden, sondern nur die fehlgeschlagenen.
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/PoiCategories.php';

use App\Support\Db;
use App\Support\PoiCategories;

const GERMANY_BBOX = '47.27,5.87,55.06,15.04'; // minLat,minLon,maxLat,maxLon
const MAX_ATTEMPTS = 4;

/**
 * Fragt EINEN Tag-Filter für ganz Deutschland bei Overpass ab, mit
 * Wiederholungsversuchen bei vorübergehenden Fehlern.
 *
 * Ursprünglich mit area["ISO3166-1"="DE"] geplant (exakte Staatsgrenze statt
 * Rechteck) — beim Testen stellte sich heraus, dass allein das Auflösen
 * dieser Grenze als Polygon so teuer ist, dass es selbst bei nur 3
 * gewünschten Treffern in einen Timeout lief. Eine simple Bounding-Box ist
 * für Overpass drastisch billiger; die Ungenauigkeit (ein schmaler Streifen
 * jenseits der Grenze zu Nachbarländern wird mit erfasst) ist für eine
 * "was gibt's in der Nähe"-Funktion vernachlässigbar.
 *
 * Zweite Lektion aus der Praxis: ursprünglich lief hier EINE Abfrage pro
 * Kategorie, mit allen ihren Tags (z.B. bei 'food_drink' fünf: bar, pub,
 * cafe, restaurant, biergarten) per ODER verknüpft. Für Kategorien mit
 * vielen und/oder bundesweit sehr häufigen Tags lief das zuverlässig in
 * Overpass' eigenen 180s-Query-Timeout ("Query timed out ... after 181
 * seconds", nicht bloß ein überlasteter Server, 4/4 Versuche hintereinander
 * identisch) — die kombinierte Selektivität mehrerer ODER-Zweige über ganz
 * Deutschland ist schlicht zu teuer, egal wie oft man es wiederholt. Eine
 * Abfrage pro einzelnem Tag ist jeweils deutlich billiger und zuverlässiger;
 * mehr Anfragen insgesamt, aber jede einzelne mit realistischer Chance,
 * überhaupt durchzulaufen.
 */
function fetchTagFromOverpass(string $tagFilter): ?array
{
    $query = '[out:json][timeout:180];(node[' . $tagFilter . '](' . GERMANY_BBOX . ');way[' . $tagFilter . '](' . GERMANY_BBOX . '););out center;';

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

        // OSM-Namen sind ungeprüften Freitext ohne Längenbegrenzung — einzelne
        // Einträge (offizielle Firmierungen, mehrsprachige Namen) überschreiten
        // die 200 Zeichen der Spalte. Ohne Kappung bricht INSERT mit "Data too
        // long" ab und reißt den kompletten Kategorie-Import mit sich (genau so
        // beobachtet: der Lauf starb mitten in 'doctor', 'supermarket' und
        // 'transit' wurden nie erreicht).
        $name = $el['tags']['name'] ?? null;
        if ($name !== null && mb_strlen($name) > 200) {
            $name = mb_substr($name, 0, 200);
        }

        $rows[] = [
            'osm_id' => (int) $el['id'],
            'osm_type' => $el['type'],
            'lat' => (float) $lat,
            'lon' => (float) $lon,
            'name' => $name,
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

$requestedCategories = array_slice($argv, 1);
if ($requestedCategories !== []) {
    $unknown = array_diff($requestedCategories, array_keys(PoiCategories::CATEGORIES));
    if ($unknown !== []) {
        fwrite(STDERR, 'Unbekannte Kategorie(n): ' . implode(', ', $unknown) . "\n");
        exit(1);
    }
}

foreach (PoiCategories::CATEGORIES as $category => $tagFilters) {
    if ($requestedCategories !== [] && !in_array($category, $requestedCategories, true)) {
        continue;
    }
    echo "Lade Kategorie '$category' für ganz Deutschland...\n";

    $categoryTotal = 0;
    $anyTagFailed = false;
    foreach ($tagFilters as $tagFilter) {
        echo "  Tag '$tagFilter' ... ";
        $elements = fetchTagFromOverpass($tagFilter);
        if ($elements === null) {
            echo "fehlgeschlagen, übersprungen.\n";
            $anyTagFailed = true;
            sleep(2);
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
        $categoryTotal += count($rows);
        echo count($rows) . " Treffer.\n";

        sleep(2); // kurze Pause zwischen den Tags, aus Rücksicht auf Overpass
    }

    echo '  Kategorie ' . ($anyTagFailed ? 'teilweise' : 'vollständig') . ": $categoryTotal Standorte importiert.\n";
}

echo "Fertig.\n";
