<?php

namespace App\Http;

use App\Support\Db;

final class PoiController
{
    private const CACHE_LIFETIME_DAYS = 21; // POI-Standorte ändern sich selten
    private const OVERPASS_MIN_INTERVAL_SECONDS = 1.1;
    private const GRID_DEG = 0.02; // BBox-Rundung fürs Caching, ca. 1,5km

    /**
     * Kategorie -> Overpass-Filter (jeweils als "(...)"-Block, mehrere
     * Tag-Alternativen werden einfach aneinandergereiht).
     */
    private const CATEGORIES = [
        'playground'  => ['leisure=playground'],
        'food_drink'  => ['amenity=bar', 'amenity=pub', 'amenity=cafe', 'amenity=restaurant', 'amenity=biergarten'],
        'leisure'     => ['leisure=fitness_centre', 'leisure=sports_centre', 'leisure=swimming_pool', 'amenity=cinema'],
        'university'  => ['amenity=university', 'amenity=college'],
        'school'      => ['amenity=school', 'amenity=kindergarten'],
        'doctor'      => ['amenity=doctors', 'amenity=pharmacy'],
        'supermarket' => ['shop=supermarket'],
        'transit'     => ['highway=bus_stop', 'railway=tram_stop', 'railway=station'],
    ];

    /** GET /api/poi?category=…&bbox=minLon,minLat,maxLon,maxLat */
    public function search(): void
    {
        $category = (string) ($_GET['category'] ?? '');
        if (!isset(self::CATEGORIES[$category])) {
            http_response_code(422);
            $this->json(['error' => 'Unbekannte Kategorie.']);
            return;
        }

        $bboxParts = explode(',', $_GET['bbox'] ?? '');
        if (count($bboxParts) !== 4) {
            http_response_code(422);
            $this->json(['error' => 'bbox erwartet: minLon,minLat,maxLon,maxLat']);
            return;
        }
        [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', $bboxParts);

        // Auf ein grobes Raster runden, damit benachbarte Kartenschwenks
        // denselben Cache-Eintrag treffen, statt jedes Mal neu abzufragen.
        $round = fn (float $v, float $dir) => $dir < 0
            ? floor($v / self::GRID_DEG) * self::GRID_DEG
            : ceil($v / self::GRID_DEG) * self::GRID_DEG;
        $gMinLon = $round($minLon, -1); $gMinLat = $round($minLat, -1);
        $gMaxLon = $round($maxLon, 1); $gMaxLat = $round($maxLat, 1);
        $cacheKey = $category . ':' . implode(',', [$gMinLon, $gMinLat, $gMaxLon, $gMaxLat]);

        $db = Db::get();
        $cached = $db->prepare(
            'SELECT response_json FROM poi_cache WHERE cache_key = :k AND created_at >= :cutoff'
        );
        $cached->execute([
            'k' => $cacheKey,
            'cutoff' => date('Y-m-d H:i:s', time() - self::CACHE_LIFETIME_DAYS * 86400),
        ]);
        $hit = $cached->fetchColumn();
        if ($hit !== false) {
            $this->json(['points' => json_decode($hit, true)]);
            return;
        }

        // Kein Treffer — nur live nachfragen, wenn seit der letzten eigenen
        // Overpass-Anfrage (über alle Kategorien hinweg) genug Zeit vergangen
        // ist. Sonst leer antworten, der nächste Kartenschwenk holt es nach.
        $lastCall = $db->query('SELECT MAX(created_at) FROM poi_cache')->fetchColumn();
        if ($lastCall !== null && (time() - strtotime($lastCall)) < self::OVERPASS_MIN_INTERVAL_SECONDS) {
            $this->json(['points' => [], 'hinweis' => 'kurz warten']);
            return;
        }

        $points = $this->fetchFromOverpass(self::CATEGORIES[$category], $gMinLon, $gMinLat, $gMaxLon, $gMaxLat);

        if ($points === null) {
            // Anfrage fehlgeschlagen (z.B. Overpass überlastet) — NICHT
            // cachen, sonst wäre ein Fehlversuch für Tage als "leere
            // Gegend" eingefroren. Nächster Kartenschwenk versucht's erneut.
            $this->json(['points' => [], 'hinweis' => 'Anfrage fehlgeschlagen, bitte später erneut versuchen']);
            return;
        }

        $store = $db->prepare(
            'INSERT INTO poi_cache (cache_key, category, response_json) VALUES (:k, :cat, :json)
             ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), created_at = NOW()'
        );
        $store->execute(['k' => $cacheKey, 'cat' => $category, 'json' => json_encode($points, JSON_UNESCAPED_UNICODE)]);

        $this->json(['points' => $points]);
    }

    /** @param string[] $tagFilters @return array<int, array{lat:float,lon:float,name:?string}>|null null = Anfrage fehlgeschlagen */
    private function fetchFromOverpass(array $tagFilters, float $minLon, float $minLat, float $maxLon, float $maxLat): ?array
    {
        // Nur `node` abfragen, kein zusätzliches `way` — die große Mehrheit
        // dieser POI-Arten ist in OSM als einfacher Punkt getaggt, und jedes
        // zusätzliche `way[...]` verdoppelt die Anzahl der Unterabfragen
        // (plus teure Geometrieauflösung für "out center"). Bei mehreren
        // Tag-Alternativen pro Kategorie führte das beim öffentlichen
        // Overpass-Server zuverlässig zu Timeouts (504) statt Ergebnissen.
        $bbox = "$minLat,$minLon,$maxLat,$maxLon"; // Overpass: lat,lon,lat,lon
        $clauses = implode('', array_map(fn (string $t) => "node[$t]($bbox);", $tagFilters));
        $query = "[out:json][timeout:20];($clauses);out 200;";

        $ch = curl_init('https://overpass-api.de/api/interpreter');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['data' => $query],
            CURLOPT_HTTPHEADER => ['User-Agent: habitat-feedback-webapp/1.0 (dev)'],
            CURLOPT_TIMEOUT => 25,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        // Overpass antwortet bei Überlastung manchmal mit HTTP 200 und einer
        // HTML-Fehlerseite statt JSON — json_decode liefert dann null,
        // das muss von einem echten "keine Treffer" (leeres elements-Array)
        // unterschieden werden.
        if (!is_array($data) || !array_key_exists('elements', $data)) {
            return null;
        }

        $points = [];
        foreach ($data['elements'] as $el) {
            if (!isset($el['lat'], $el['lon'])) {
                continue;
            }
            $points[] = ['lat' => $el['lat'], 'lon' => $el['lon'], 'name' => $el['tags']['name'] ?? null];
        }
        return $points;
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
