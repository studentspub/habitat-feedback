<?php

namespace App\Http;

use App\Support\Db;

final class OepnvController
{
    private const MAX_RADIUS = 1500;
    private const MAX_PUNKTE = 3000;

    /**
     * GET /api/oepnv?lat=&lon=&radius=600 — ÖPNV-Anbindung um einen Punkt
     * (Issue #24): Stationen im Umkreis mit ihren Abfahrten an einem normalen
     * Werktag (GTFS Deutschland, bin/import_gtfs.php), beste zuerst, plus Summe.
     */
    public function umkreis(): void
    {
        $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
        $lon = isset($_GET['lon']) ? (float) $_GET['lon'] : null;
        $radius = min(self::MAX_RADIUS, max(100, (int) ($_GET['radius'] ?? 600)));
        if ($lat === null || $lon === null) {
            http_response_code(422);
            $this->json(['error' => 'lat und lon erforderlich.']);
            return;
        }
        $dLat = $radius / 111320;
        $dLon = $radius / (111320 * cos(deg2rad($lat)));
        $rechteck = sprintf('POLYGON((%1$.7F %2$.7F,%3$.7F %2$.7F,%3$.7F %4$.7F,%1$.7F %4$.7F,%1$.7F %2$.7F))',
            $lon - $dLon, $lat - $dLat, $lon + $dLon, $lat + $dLat);
        $db = Db::get();
        $stmt = $db->prepare(
            'SELECT name, abfahrten, abfahrten_6_20, verkehrsmittel,
                    ROUND(6371000 * 2 * ASIN(SQRT(POW(SIN(RADIANS(lat - :lat_a) / 2), 2)
                    + COS(RADIANS(:lat_b)) * COS(RADIANS(lat)) * POW(SIN(RADIANS(lon - :lon_a) / 2), 2)))) AS entfernung
             FROM oepnv_haltestellen
             WHERE MBRContains(ST_GeomFromText(:rechteck), pt)
             HAVING entfernung <= :radius
             ORDER BY abfahrten DESC'
        );
        $stmt->execute(['lat_a' => $lat, 'lat_b' => $lat, 'lon_a' => $lon, 'rechteck' => $rechteck, 'radius' => $radius]);
        $stationen = $stmt->fetchAll();
        $stand = $db->query('SELECT stichtag FROM oepnv_stand WHERE id = 1')->fetchColumn();

        $this->json([
            'radius' => $radius,
            'stichtag' => $stand ?: null,
            'anzahl' => count($stationen),
            'abfahrten_summe' => array_sum(array_column($stationen, 'abfahrten')),
            'stationen' => array_map(fn($s) => [
                'name' => $s['name'], 'abfahrten' => (int) $s['abfahrten'], 'abfahrten_6_20' => (int) $s['abfahrten_6_20'],
                'verkehrsmittel' => $s['verkehrsmittel'], 'entfernung' => (int) $s['entfernung'],
            ], array_slice($stationen, 0, 5)),
            'quelle' => 'Fahrplandaten: DELFI e.V. über GTFS.de (CC 4.0)',
        ]);
    }

    /**
     * GET /api/oepnv/punkte?bbox=minLon,minLat,maxLon,maxLat — Stationen im
     * Ausschnitt für die Kartenebene „ÖPNV-Takt“, am besten angebundene zuerst.
     */
    public function punkte(): void
    {
        $b = array_map('floatval', explode(',', $_GET['bbox'] ?? ''));
        if (count($b) !== 4) {
            http_response_code(422);
            $this->json(['error' => 'bbox erwartet: minLon,minLat,maxLon,maxLat']);
            return;
        }
        [$minLon, $minLat, $maxLon, $maxLat] = $b;
        $rechteck = sprintf('POLYGON((%1$.7F %2$.7F,%3$.7F %2$.7F,%3$.7F %4$.7F,%1$.7F %4$.7F,%1$.7F %2$.7F))', $minLon, $minLat, $maxLon, $maxLat);
        $stmt = Db::get()->prepare(
            'SELECT lat, lon, name, abfahrten, abfahrten_6_20, verkehrsmittel FROM oepnv_haltestellen
             WHERE MBRContains(ST_GeomFromText(:rechteck), pt)
             ORDER BY abfahrten DESC LIMIT ' . self::MAX_PUNKTE
        );
        $stmt->execute(['rechteck' => $rechteck]);
        $punkte = array_map(fn($p) => [
            round((float) $p['lat'], 5), round((float) $p['lon'], 5), $p['name'], (int) $p['abfahrten'], (int) $p['abfahrten_6_20'], $p['verkehrsmittel'],
        ], $stmt->fetchAll());
        $this->json(['punkte' => $punkte, 'gekuerzt' => count($punkte) === self::MAX_PUNKTE]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (http_response_code() === 200) {
            header('Cache-Control: public, max-age=86400');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
