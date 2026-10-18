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
 * Fallback-Ebene für Tags, die selbst einzeln (siehe fetchTagFromOverpass)
 * bundesweit noch zu teuer für Overpass sind — bislang nur bei
 * highway=bus_stop beobachtet (mit Abstand die häufigste ÖPNV-
 * Haltestellenart in OSM). Grenzen einmalig über Nominatim ermittelt
 * (minLat,minLon,maxLat,maxLon, wie GERMANY_BBOX); Überlappungen an
 * Landesgrenzen sind unschädlich, poi_locations verhindert Duplikate über
 * den UNIQUE-Key (category, osm_type, osm_id).
 */
const BUNDESLAND_BBOXES = [
    'Baden-Württemberg' => '47.5324012,7.5117461,49.7912941,10.4956269',
    'Bayern' => '47.2701114,8.9763654,50.5647142,13.8396495',
    'Berlin' => '52.3382448,13.0883450,52.6755087,13.7611609',
    'Brandenburg' => '51.3590081,11.2657725,53.5590617,14.7658159',
    'Bremen' => '53.0111644,8.4815929,53.5984187,8.9908300',
    'Hamburg' => '53.3951118,8.1044993,54.0276500,10.3252805',
    'Hessen' => '49.3952716,7.7724673,51.6577889,10.2364142',
    'Mecklenburg-Vorpommern' => '53.1103525,10.5936136,54.8849662,14.4122208',
    'Niedersachsen' => '51.2950693,6.3459138,54.1378463,11.5981180',
    'Nordrhein-Westfalen' => '50.3226897,5.8663153,52.5314935,9.4617417',
    'Rheinland-Pfalz' => '48.9664472,6.1122591,50.9423049,8.5083135',
    'Saarland' => '49.1119512,6.3557914,49.6394270,7.4048307',
    'Sachsen' => '50.1713271,11.8714837,51.6849651,15.0419309',
    'Sachsen-Anhalt' => '50.9378455,10.5608411,53.0417792,13.1867940',
    'Schleswig-Holstein' => '53.3598160,7.5211615,55.0991610,11.6723860',
    'Thüringen' => '50.2043467,9.8769608,51.6492842,12.6539178',
];

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
function fetchTagFromOverpass(string $tagFilter, string $bbox = GERMANY_BBOX): ?array
{
    $query = '[out:json][timeout:180];(node[' . $tagFilter . '](' . $bbox . ');way[' . $tagFilter . '](' . $bbox . '););out center;';

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
            CURLOPT_HTTPHEADER => ['User-Agent: habitat-feedback-import/1.0 (+https://habitat-feedback.de; cihanaldanmaz@gmail.com)'],
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
 * Fallback für Tags, die selbst als Einzelabfrage bundesweit in Overpass'
 * eigenen Timeout laufen: dieselbe Abfrage einmal je Bundesland statt
 * einmal für ganz Deutschland. Gibt null zurück, wenn auch nur ein
 * Bundesland komplett fehlschlägt — lieber einen erneuten Gesamtlauf
 * anstoßen als eine Kategorie leise unvollständig in der DB stehen lassen.
 *
 * @return array<int, mixed>|null
 */
function fetchTagByBundesland(string $tagFilter): ?array
{
    $allElements = [];
    foreach (BUNDESLAND_BBOXES as $name => $bbox) {
        echo "\n    $name ... ";
        $elements = fetchTagFromOverpass($tagFilter, $bbox);
        if ($elements === null) {
            echo "fehlgeschlagen.";
            return null;
        }
        echo count($elements) . ' Treffer.';
        $allElements = array_merge($allElements, $elements);
        sleep(2);
    }
    echo "\n  ";
    return $allElements;
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
 * @return array<int, array{osm_id:int, osm_type:string, lat:float, lon:float, name:?string, barrierefrei:int}>
 */
function mapElementsToRows(string $category, array $elements): array
{
    $rows = [];
    foreach ($elements as $el) {
        $lat = $el['lat'] ?? $el['center']['lat'] ?? null;
        $lon = $el['lon'] ?? $el['center']['lon'] ?? null;
        if ($lat === null || $lon === null) {
            continue;
        }

        // Beschriftung je Kategorie (PoiCategories::beschriftung, Issue #1): bei den
        // alten Kategorien der OSM-Name, bei Toiletten/Post/E-Bike eine Art-Angabe.
        // OSM-Namen sind ungeprüfter Freitext ohne Längenbegrenzung — einzelne
        // Einträge (offizielle Firmierungen, mehrsprachige Namen) überschreiten
        // die 200 Zeichen der Spalte. Ohne Kappung bricht INSERT mit "Data too
        // long" ab und reißt den kompletten Kategorie-Import mit sich (genau so
        // beobachtet: der Lauf starb mitten in 'doctor', 'supermarket' und
        // 'transit' wurden nie erreicht).
        $tags = $el['tags'] ?? [];
        $name = PoiCategories::beschriftung($category, $tags);
        if ($name !== null && mb_strlen($name) > 200) {
            $name = mb_substr($name, 0, 200);
        }

        $rows[] = [
            'osm_id' => (int) $el['id'],
            'osm_type' => $el['type'],
            'lat' => (float) $lat,
            'lon' => (float) $lon,
            'name' => $name,
            'barrierefrei' => $category === 'toilets' && PoiCategories::barrierefrei($tags) ? 1 : 0,
        ];
    }
    return $rows;
}

/**
 * Prüft die Tags eines Elements gegen einen Filterausdruck aus
 * PoiCategories::CATEGORIES, ohne Overpass (Issue #1, Modus --extrakt).
 * Verstanden werden `k=v`, `k~"regex"` und `k!~"regex"`, mit `][` verkettet,
 * also genau die Teilmenge der Overpass-Syntax, die die Kategorien benutzen.
 */
function tagsPassen(array $tags, string $filter): bool
{
    foreach (explode('][', $filter) as $teil) {
        if (preg_match('/^([\w:]+)(!~|~|=)"?(.*?)"?$/', $teil, $m) !== 1) {
            throw new RuntimeException("Filter nicht verstanden: $teil");
        }
        [, $key, $op, $wert] = $m;
        $vorhanden = $tags[$key] ?? null;
        $ok = match ($op) {
            '=' => $vorhanden === $wert,
            '~' => $vorhanden !== null && preg_match('/' . $wert . '/', $vorhanden) === 1,
            '!~' => $vorhanden === null || preg_match('/' . $wert . '/', $vorhanden) !== 1,
        };
        if (!$ok) {
            return false;
        }
    }
    return true;
}

/**
 * Mittelpunkt einer GeoJSON-Geometrie aus `osmium export`: Punkt direkt,
 * Fläche als Mittel des äußeren Rings (reicht für Gebäude), Linie der
 * mittlere Stützpunkt. Entspricht grob Overpass' `out center`.
 *
 * @return array{0: ?float, 1: ?float} [lat, lon]
 */
function mittelpunkt(?array $geometry): array
{
    $coords = $geometry['coordinates'] ?? null;
    switch ($geometry['type'] ?? '') {
        case 'Point':
            return [(float) $coords[1], (float) $coords[0]];
        case 'MultiPolygon':
            $coords = $coords[0];
            // fallthrough
        case 'Polygon':
            $ring = array_slice($coords[0], 0, -1);
            $n = count($ring);
            if ($n === 0) {
                return [null, null];
            }
            return [array_sum(array_column($ring, 1)) / $n, array_sum(array_column($ring, 0)) / $n];
        case 'LineString':
            $p = $coords[intdiv(count($coords), 2)];
            return [(float) $p[1], (float) $p[0]];
        default:
            return [null, null];
    }
}

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO poi_locations (category, osm_id, osm_type, lat, lon, name, barrierefrei)
     VALUES (:category, :osm_id, :osm_type, :lat, :lon, :name, :barrierefrei)
     ON DUPLICATE KEY UPDATE lat = VALUES(lat), lon = VALUES(lon), name = VALUES(name),
        barrierefrei = VALUES(barrierefrei), imported_at = NOW()'
);

// --extrakt=/pfad/datei.osm.pbf (Issue #1): statt Overpass den lokalen Geofabrik-
// Extrakt lesen, vorher mit `osmium tags-filter` auf die gewünschten Tags
// eingedampft (siehe BETRIEB.md). Dieselben Filterausdrücke, dieselbe Beschriftung,
// dieselbe Tabelle - nur ohne Abhängigkeit von Overpass' Tageslaune (502/504).
$extrakt = null;
foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "Aufruf: php bin/import_poi.php [--extrakt=DATEI.osm.pbf] [kategorie ...]\n"
            . "  ohne Kategorien: alle aus PoiCategories::CATEGORIES\n"
            . "  ohne --extrakt: Abfrage je Tag bei Overpass (ganz Deutschland)\n"
            . "  mit --extrakt: Elemente aus der Datei lesen (osmium export), keine Netzanfrage\n";
        exit(0);
    }
    if (str_starts_with($arg, '--extrakt=')) {
        $extrakt = substr($arg, strlen('--extrakt='));
        unset($argv[$i]);
    }
}
$argv = array_values($argv);

$requestedCategories = array_slice($argv, 1);
if ($requestedCategories !== []) {
    $unknown = array_diff($requestedCategories, array_keys(PoiCategories::CATEGORIES));
    if ($unknown !== []) {
        fwrite(STDERR, 'Unbekannte Kategorie(n): ' . implode(', ', $unknown) . "\n");
        exit(1);
    }
}

if ($extrakt !== null) {
    if (!is_file($extrakt)) {
        fwrite(STDERR, "Extrakt nicht gefunden: $extrakt\n");
        exit(1);
    }
    $kategorien = $requestedCategories !== [] ? $requestedCategories : array_keys(PoiCategories::CATEGORIES);
    echo 'Lese ' . $extrakt . ' für ' . implode(', ', $kategorien) . " ...\n";
    // geojsonseq: eine Feature-Zeile je Element, @type/@id als Properties; Relationen
    // (Multipolygon-Gebäude, sehr selten) lassen wir aus - die Spalte osm_type kennt nur node/way,
    // und eine Relations-ID könnte mit einer Way-ID zusammenfallen.
    // Nur point und polygon: geschlossene Wege gibt osmium sonst zusätzlich als
    // LineString aus, dann käme jedes Gebäude doppelt an (der UNIQUE-Key fängt
    // das zwar ab, aber die Zählung stimmte nicht mehr).
    $cmd = 'osmium export -f geojsonseq -a type,id --geometry-types=point,polygon -o - ' . escapeshellarg($extrakt);
    $h = popen($cmd, 'r');
    if ($h === false) {
        fwrite(STDERR, "osmium export ließ sich nicht starten.\n");
        exit(1);
    }
    $zaehler = array_fill_keys($kategorien, 0);
    $gelesen = 0;
    $db->beginTransaction();
    while (($zeile = fgets($h)) !== false) {
        $f = json_decode(ltrim($zeile, "\x1e"), true); // RS-Zeichen vor jeder geojsonseq-Zeile
        if (!is_array($f) || !isset($f['properties'])) {
            continue;
        }
        $gelesen++;
        $tags = $f['properties'];
        $typ = $tags['@type'] ?? 'node';
        $id = (int) ($tags['@id'] ?? 0);
        unset($tags['@type'], $tags['@id']);
        if (!in_array($typ, ['node', 'way'], true) || $id === 0) {
            continue;
        }
        [$lat, $lon] = mittelpunkt($f['geometry'] ?? null);
        if ($lat === null) {
            continue;
        }
        foreach ($kategorien as $category) {
            foreach (PoiCategories::CATEGORIES[$category] as $filter) {
                if (!tagsPassen($tags, $filter)) {
                    continue;
                }
                $name = PoiCategories::beschriftung($category, $tags);
                $upsert->execute([
                    'category' => $category,
                    'osm_id' => $id,
                    'osm_type' => $typ,
                    'lat' => $lat,
                    'lon' => $lon,
                    'name' => $name !== null ? mb_substr($name, 0, 200) : null,
                    'barrierefrei' => $category === 'toilets' && PoiCategories::barrierefrei($tags) ? 1 : 0,
                ]);
                $zaehler[$category]++;
                break;
            }
        }
        if ($gelesen % 20000 === 0) {
            $db->commit();
            $db->beginTransaction();
            echo "  $gelesen Elemente gelesen ...\n";
        }
    }
    $db->commit();
    $exit = pclose($h);
    if ($exit !== 0) {
        fwrite(STDERR, "osmium export endete mit Exit-Code $exit.\n");
        exit(1);
    }
    echo "$gelesen Elemente gelesen.\n";
    $bestand = $db->prepare('SELECT COUNT(*) FROM poi_locations WHERE category = ?');
    foreach ($zaehler as $category => $n) {
        $bestand->execute([$category]);
        echo "  $category: $n Treffer verarbeitet, " . $bestand->fetchColumn() . " Standorte in der Tabelle.\n";
    }
    echo "Fertig.\n";
    exit(0);
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
            echo "bundesweit fehlgeschlagen, versuche je Bundesland...";
            $elements = fetchTagByBundesland($tagFilter);
        }
        if ($elements === null) {
            echo "fehlgeschlagen, übersprungen.\n";
            $anyTagFailed = true;
            sleep(2);
            continue;
        }

        $rows = mapElementsToRows($category, $elements);
        foreach ($rows as $row) {
            $upsert->execute([
                'category' => $category,
                'osm_id' => $row['osm_id'],
                'osm_type' => $row['osm_type'],
                'lat' => $row['lat'],
                'lon' => $row['lon'],
                'name' => $row['name'],
                'barrierefrei' => $row['barrierefrei'],
            ]);
        }
        $categoryTotal += count($rows);
        echo count($rows) . " Treffer.\n";

        sleep(2); // kurze Pause zwischen den Tags, aus Rücksicht auf Overpass
    }

    echo '  Kategorie ' . ($anyTagFailed ? 'teilweise' : 'vollständig') . ": $categoryTotal Standorte importiert.\n";
}

echo "Fertig.\n";
