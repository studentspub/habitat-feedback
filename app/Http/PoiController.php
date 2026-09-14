<?php

namespace App\Http;

use App\Support\Db;
use App\Support\PoiCategories;

final class PoiController
{
    private const MAX_POINTS = 500; // Sicherheitsdeckel bei sehr dichten Innenstädten

    /**
     * GET /api/poi?category=…&bbox=minLon,minLat,maxLon,maxLat
     *
     * Liest ausschließlich aus poi_locations (einmalig importiert, siehe
     * bin/import_poi.php) — kein Live-Aufruf bei Overpass mehr. Genau wie
     * bei climate_normals/air_stations: die Standorte ändern sich selten
     * genug, dass ein periodischer Neu-Import reicht, und die Karte hängt
     * dadurch beim normalen Gebrauch an keinem fremden Dienst mehr.
     */
    public function search(): void
    {
        $category = (string) ($_GET['category'] ?? '');
        if (!array_key_exists($category, PoiCategories::CATEGORIES)) {
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
        $stmt->execute(['cat' => $category, 'minLat' => $minLat, 'maxLat' => $maxLat, 'minLon' => $minLon, 'maxLon' => $maxLon]);

        $this->json(['points' => $stmt->fetchAll()]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
