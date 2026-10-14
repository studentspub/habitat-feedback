<?php

namespace App\Http;

use App\Support\Db;
use App\Support\Laea;

final class ZensusController
{
    private const STUFEN = ['100m' => 100, '1km' => 1000, '10km' => 10000];
    // Feldname wie im früheren ArcGIS-Dienst (die Karte bleibt unverändert) => Spalte
    private const FELDER = [
        'Einwohner' => 'einwohner',
        'Durchschnittsalter' => 'alter_d',
        'durchschnMieteQM' => 'miete_qm',
        'Eigentuemerquote' => 'eigentum',
        'Leerstandsquote' => 'leerstand',
    ];
    private const MAX_ZELLEN = 4000;

    /**
     * GET /api/zensus?stufe=100m|1km|10km&feld=Einwohner&bbox=minLon,minLat,maxLon,maxLat
     * -> GeoJSON-FeatureCollection, je Zelle ein Quadrat mit {feld: wert} —
     * dasselbe Format, das die Karte bisher vom ArcGIS-Dienst bekam (Issue #23).
     * Quelle: Zensus 2022 Gitterdaten (Destatis), lokal in zensus_gitter.
     */
    public function zellen(): void
    {
        $stufe = self::STUFEN[$_GET['stufe'] ?? ''] ?? null;
        $spalte = self::FELDER[$_GET['feld'] ?? ''] ?? null;
        $b = array_map('floatval', explode(',', $_GET['bbox'] ?? ''));
        if ($stufe === null || $spalte === null || count($b) !== 4) {
            http_response_code(422);
            $this->json(['error' => 'stufe (100m|1km|10km), feld und bbox (minLon,minLat,maxLon,maxLat) erforderlich.']);
            return;
        }
        [$minLon, $minLat, $maxLon, $maxLat] = $b;

        // Ausschnitt -> LAEA-Rechteck: Ecken und Kantenmitten, weil die Ränder
        // eines Längen-/Breiten-Rechtecks in LAEA leicht gekrümmt sind.
        $xs = $ys = [];
        foreach ([$minLat, ($minLat + $maxLat) / 2, $maxLat] as $la) {
            foreach ([$minLon, ($minLon + $maxLon) / 2, $maxLon] as $lo) {
                [$x, $y] = Laea::fromLatLon($la, $lo);
                $xs[] = $x;
                $ys[] = $y;
            }
        }
        $minX = min($xs) - $stufe;
        $maxX = max($xs) + $stufe;
        $minY = min($ys) - $stufe;
        $maxY = max($ys) + $stufe;

        $stmt = Db::get()->prepare(
            "SELECT x, y, $spalte AS wert FROM zensus_gitter
             WHERE aufloesung = :stufe
               AND kx BETWEEN :kx1 AND :kx2 AND ky BETWEEN :ky1 AND :ky2
               AND x BETWEEN :x1 AND :x2 AND y BETWEEN :y1 AND :y2
               AND $spalte IS NOT NULL
             LIMIT " . self::MAX_ZELLEN
        );
        $stmt->execute([
            'stufe' => $stufe,
            'kx1' => intdiv((int) $minX, 10000), 'kx2' => intdiv((int) $maxX, 10000),
            'ky1' => intdiv((int) $minY, 10000), 'ky2' => intdiv((int) $maxY, 10000),
            'x1' => $minX, 'x2' => $maxX, 'y1' => $minY, 'y2' => $maxY,
        ]);

        $feld = $_GET['feld'];
        $halb = $stufe / 2;
        $features = [];
        foreach ($stmt->fetchAll() as $z) {
            $ring = [];
            foreach ([[-1, -1], [1, -1], [1, 1], [-1, 1], [-1, -1]] as [$dx, $dy]) {
                [$la, $lo] = Laea::toLatLon($z['x'] + $dx * $halb, $z['y'] + $dy * $halb);
                $ring[] = [round($lo, 6), round($la, 6)];
            }
            $features[] = [
                'type' => 'Feature',
                'properties' => [$feld => $spalte === 'einwohner' ? (int) $z['wert'] : round((float) $z['wert'], 2)],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
            ];
        }
        $this->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (http_response_code() === 200) {
            header('Cache-Control: public, max-age=86400'); // Zensus 2022 ändert sich nicht
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
