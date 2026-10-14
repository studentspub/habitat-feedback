<?php

namespace App\Http;

use App\Support\Db;
use App\Support\PoiCategories;

final class PoiController
{
    private const MAX_POINTS = 500; // Sicherheitsdeckel bei sehr dichten Innenstädten (je Kategorie)

    /**
     * GET /api/poi?categories=a,b,c&bbox=minLon,minLat,maxLon,maxLat
     *   -> {"categories": {"a": [{lat, lon, name}, …], …}}
     * GET /api/poi?category=a&bbox=…  (alter Einzelaufruf) -> {"points": […]}
     *
     * Liest ausschließlich aus poi_locations (einmalig importiert, siehe
     * bin/import_poi.php) — kein Live-Aufruf bei Overpass mehr. Genau wie
     * bei climate_normals/air_stations: die Standorte ändern sich selten
     * genug, dass ein periodischer Neu-Import reicht, und die Karte hängt
     * dadurch beim normalen Gebrauch an keinem fremden Dienst mehr.
     *
     * Mehrere Kategorien in einer Anfrage (Issue #13): Browser öffnen nur
     * ~6 Verbindungen je Server, acht Einzelanfragen pro Kartenschwenk
     * standen sonst Schlange.
     */
    public function search(): void
    {
        $einzeln = isset($_GET['category']) && !isset($_GET['categories']);
        $raw = (string) ($einzeln ? $_GET['category'] : ($_GET['categories'] ?? ''));
        $categories = array_values(array_unique(array_filter(explode(',', $raw))));
        if ($categories === [] || array_diff($categories, array_keys(PoiCategories::CATEGORIES)) !== []) {
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

        $stmt = Db::get()->prepare(
            'SELECT lat, lon, name FROM poi_locations
             WHERE category = :cat AND lat BETWEEN :minLat AND :maxLat AND lon BETWEEN :minLon AND :maxLon
             LIMIT ' . self::MAX_POINTS
        );
        $proKategorie = [];
        foreach ($categories as $category) {
            $stmt->execute(['cat' => $category, 'minLat' => $minLat, 'maxLat' => $maxLat, 'minLon' => $minLon, 'maxLon' => $maxLon]);
            // 5 Nachkommastellen ≈ 1 m — genauer zeichnet die Karte ohnehin nicht.
            $proKategorie[$category] = array_map(fn($p) => [
                'lat' => round((float) $p['lat'], 5),
                'lon' => round((float) $p['lon'], 5),
                'name' => $p['name'],
            ], $stmt->fetchAll());
        }

        // Referenzdaten (Neu-Import höchstens monatlich): der Browser darf die
        // Antwort einen Tag lang wiederverwenden. Greift, weil die Karte die
        // bbox auf ein 0,01°-Raster rundet.
        header('Cache-Control: public, max-age=86400');
        $this->json($einzeln ? ['points' => $proKategorie[$categories[0]]] : ['categories' => $proKategorie]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
