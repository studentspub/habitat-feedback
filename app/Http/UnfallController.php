<?php

namespace App\Http;

use App\Support\Db;

final class UnfallController
{
    private const MAX_RADIUS = 1000;
    private const MAX_PUNKTE = 3000;

    /**
     * GET /api/unfaelle?lat=&lon=&radius=500 — Unfälle mit Personenschaden
     * im Umkreis (Meter, höchstens 1000) aus dem Unfallatlas (Issue #20):
     * Anzahl gesamt, mit Getöteten, mit Schwerverletzten, mit Rad- bzw.
     * Fußgängerbeteiligung, Jahresspanne. Vorfilter über den Spatial-Index
     * (Rechteck), dann echte Entfernung (Haversine).
     */
    public function umkreis(): void
    {
        $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
        $lon = isset($_GET['lon']) ? (float) $_GET['lon'] : null;
        $radius = min(self::MAX_RADIUS, max(50, (int) ($_GET['radius'] ?? 500)));
        if ($lat === null || $lon === null) {
            http_response_code(422);
            $this->json(['error' => 'lat und lon erforderlich.']);
            return;
        }

        $dLat = $radius / 111320;
        $dLon = $radius / (111320 * cos(deg2rad($lat)));
        $rechteck = sprintf('POLYGON((%1$.7F %2$.7F,%3$.7F %2$.7F,%3$.7F %4$.7F,%1$.7F %4$.7F,%1$.7F %2$.7F))',
            $lon - $dLon, $lat - $dLat, $lon + $dLon, $lat + $dLat);

        $stmt = Db::get()->prepare(
            'SELECT COUNT(*) AS gesamt, SUM(kategorie = 1) AS getoetet, SUM(kategorie = 2) AS schwer,
                    SUM(rad) AS rad, SUM(fuss) AS fuss, MIN(jahr) AS von, MAX(jahr) AS bis
             FROM unfaelle
             WHERE MBRContains(ST_GeomFromText(:rechteck), pt)
               AND 6371000 * 2 * ASIN(SQRT(POW(SIN(RADIANS(lat - :lat_a) / 2), 2)
                   + COS(RADIANS(:lat_b)) * COS(RADIANS(lat)) * POW(SIN(RADIANS(lon - :lon_a) / 2), 2))) <= :radius'
        );
        $stmt->execute(['rechteck' => $rechteck, 'lat_a' => $lat, 'lat_b' => $lat, 'lon_a' => $lon, 'radius' => $radius]);
        $r = $stmt->fetch();
        $jahre = Db::get()->query('SELECT MIN(jahr), MAX(jahr) FROM unfaelle')->fetch(\PDO::FETCH_NUM);

        $this->json([
            'radius' => $radius,
            'jahre' => $jahre[0] !== null ? $jahre[0] . '–' . $jahre[1] : null,
            'gesamt' => (int) $r['gesamt'],
            'getoetet' => (int) $r['getoetet'],
            'schwer' => (int) $r['schwer'],
            'rad' => (int) $r['rad'],
            'fuss' => (int) $r['fuss'],
            'quelle' => 'Statistische Ämter des Bundes und der Länder, Unfallatlas (dl-de/by-2.0)',
        ]);
    }

    /**
     * GET /api/unfaelle/punkte?bbox=minLon,minLat,maxLon,maxLat — Unfallorte
     * im Ausschnitt für die Kartenebene (nur ab Stadtteil-Zoom sinnvoll;
     * höchstens 3000 Punkte, schwerste zuerst).
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
            'SELECT lat, lon, jahr, kategorie, rad, fuss FROM unfaelle
             WHERE MBRContains(ST_GeomFromText(:rechteck), pt)
             ORDER BY kategorie ASC LIMIT ' . self::MAX_PUNKTE
        );
        $stmt->execute(['rechteck' => $rechteck]);
        $punkte = array_map(fn($p) => [
            round((float) $p['lat'], 5), round((float) $p['lon'], 5), (int) $p['jahr'], (int) $p['kategorie'], (int) $p['rad'], (int) $p['fuss'],
        ], $stmt->fetchAll());
        $this->json(['punkte' => $punkte, 'gekuerzt' => count($punkte) === self::MAX_PUNKTE]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (http_response_code() === 200) {
            header('Cache-Control: public, max-age=86400'); // Unfallatlas erscheint einmal im Jahr
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
