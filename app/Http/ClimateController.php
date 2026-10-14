<?php

namespace App\Http;

use App\Support\Db;

final class ClimateController
{
    private const MONTH_KEYS = ['jan', 'feb', 'mar', 'apr', 'mai', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dez'];

    /**
     * GET /api/climate?lat=&lon= — nächstgelegene DWD-Station mit
     * Temperatur- bzw. Niederschlagsnormalwerten (1991-2020). Reine
     * Datenbank-Abfrage, kein externer Aufruf zur Laufzeit — die Werte
     * liegen bereits importiert vor (bin/import_climate_normals.php).
     */
    public function nearest(): void
    {
        $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
        $lon = isset($_GET['lon']) ? (float) $_GET['lon'] : null;
        if ($lat === null || $lon === null) {
            http_response_code(422);
            $this->json(['error' => 'lat und lon erforderlich.']);
            return;
        }

        // Ebene Näherung (Grad -> km) reicht für "nächste Station" völlig
        // aus — bei ~5.600 Stationen auch ohne räumlichen Index schnell
        // genug. PDO (ohne emulierte Prepares) verbietet einen Platzhalter
        // mehrfach in derselben Abfrage — jedes Vorkommen braucht einen
        // eigenen Namen, auch wenn derselbe Wert übergeben wird.
        $distanceExpr = '((lat - :lat_a) * 111) * ((lat - :lat_b) * 111) '
            . '+ ((lon - :lon_a) * 111 * COS(RADIANS(:lat_cos))) * ((lon - :lon_b) * 111 * COS(RADIANS(:lat_cos2)))';

        $temp = $this->nearestWith($distanceExpr, $lat, $lon, 'temp_jahr');
        $precip = $this->nearestWith($distanceExpr, $lat, $lon, 'precip_jahr');

        $this->json([
            'temperatur' => $temp,
            'niederschlag' => $precip,
            'zeitraum' => '1991–2020',
        ]);
    }

    private function nearestWith(string $distanceExpr, float $lat, float $lon, string $requiredField): ?array
    {
        $cols = array_map(fn (string $m) => "temp_$m, precip_$m", self::MONTH_KEYS);
        $stmt = Db::get()->prepare(
            "SELECT station_name, lat, lon, temp_jahr, precip_jahr, " . implode(', ', $cols) . ",
                    $distanceExpr AS dist_sq
             FROM climate_normals
             WHERE $requiredField IS NOT NULL
             ORDER BY dist_sq ASC LIMIT 1"
        );
        $stmt->execute([
            'lat_a' => $lat, 'lat_b' => $lat, 'lon_a' => $lon, 'lon_b' => $lon,
            'lat_cos' => $lat, 'lat_cos2' => $lat,
        ]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $months = [];
        foreach (self::MONTH_KEYS as $m) {
            $months[$m] = $requiredField === 'temp_jahr' ? $row["temp_$m"] : $row["precip_$m"];
        }

        return [
            'station' => $row['station_name'],
            'entfernung_km' => round(sqrt((float) $row['dist_sq']), 1),
            'jahreswert' => (float) $row[$requiredField],
            'monate' => $months,
        ];
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (http_response_code() === 200) {
            // Klimanormalwerte 1991-2020 ändern sich nicht; die Karte rundet lat/lon auf 0,01°, damit der Browser-Cache greift (Issue #13).
            header('Cache-Control: public, max-age=86400');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
