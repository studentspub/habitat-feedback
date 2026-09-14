<?php

namespace App\Http;

use App\Support\Db;

final class AirQualityController
{
    private const CACHE_LIFETIME_HOURS = 3;
    private const CANDIDATE_STATIONS = 6; // bei den nächsten N nach echten Daten suchen
    private const COMPONENT_NAMES = [1 => 'PM10', 3 => 'O3', 5 => 'NO2'];
    private const INDEX_LABELS = ['sehr gut', 'gut', 'mäßig', 'schlecht', 'sehr schlecht'];

    /** GET /api/air-quality?lat=&lon= */
    public function nearest(): void
    {
        $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
        $lon = isset($_GET['lon']) ? (float) $_GET['lon'] : null;
        if ($lat === null || $lon === null) {
            http_response_code(422);
            $this->json(['error' => 'lat und lon erforderlich.']);
            return;
        }

        $db = Db::get();
        // Siehe ClimateController: jeder Platzhalter muss eindeutig
        // benannt sein, auch bei mehrfacher Verwendung desselben Werts.
        $distanceExpr = '((lat - :lat_a) * 111) * ((lat - :lat_b) * 111) '
            . '+ ((lon - :lon_a) * 111 * COS(RADIANS(:lat_cos))) * ((lon - :lon_b) * 111 * COS(RADIANS(:lat_cos2)))';
        $stmt = $db->prepare(
            "SELECT station_id, station_name, city, $distanceExpr AS dist_sq
             FROM air_stations ORDER BY dist_sq ASC LIMIT " . self::CANDIDATE_STATIONS
        );
        $stmt->execute([
            'lat_a' => $lat, 'lat_b' => $lat, 'lon_a' => $lon, 'lon_b' => $lon,
            'lat_cos' => $lat, 'lat_cos2' => $lat,
        ]);
        $candidates = $stmt->fetchAll();

        foreach ($candidates as $station) {
            $reading = $this->readingForStation((int) $station['station_id']);
            if ($reading !== null) {
                $this->json([
                    'station' => $station['station_name'],
                    'ort' => $station['city'],
                    'entfernung_km' => round(sqrt((float) $station['dist_sq']), 1),
                ] + $reading);
                return;
            }
        }

        $this->json(['error' => 'Keine aktuellen Messwerte in der Nähe gefunden.']);
    }

    /** @return array{gemessen_bis:string,index:int,index_label:string,werte:array<string,float>}|null */
    private function readingForStation(int $stationId): ?array
    {
        $db = Db::get();
        $cached = $db->prepare(
            'SELECT response_json FROM air_quality_cache WHERE station_id = :id AND created_at >= :cutoff'
        );
        $cached->execute(['id' => $stationId, 'cutoff' => date('Y-m-d H:i:s', time() - self::CACHE_LIFETIME_HOURS * 3600)]);
        $hit = $cached->fetchColumn();
        if ($hit !== false) {
            $decoded = json_decode($hit, true);
            return $decoded ?: null; // im Cache kann auch "kein Ergebnis" (null) stehen
        }

        $reading = $this->fetchLatestFromUba($stationId);

        $db->prepare(
            'INSERT INTO air_quality_cache (station_id, response_json) VALUES (:id, :json)
             ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), created_at = NOW()'
        )->execute(['id' => $stationId, 'json' => json_encode($reading, JSON_UNESCAPED_UNICODE)]);

        return $reading;
    }

    private function fetchLatestFromUba(int $stationId): ?array
    {
        // Die UBA veröffentlicht Stundenwerte erst mit einigen Tagen
        // Verzögerung — "heute" liefert praktisch nie etwas, deshalb ein
        // Fenster von 10 Tagen und dann der jüngste vorhandene Eintrag.
        $from = date('Y-m-d', strtotime('-10 days'));
        $to = date('Y-m-d');
        $url = 'https://luftdaten.umweltbundesamt.de/api/air-data/v4/airquality/json?' . http_build_query([
            'date_from' => $from, 'time_from' => 1, 'date_to' => $to, 'time_to' => 24, 'station' => $stationId,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['User-Agent: habitat-feedback-webapp/1.0 (dev)'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $ok = $response !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        if (!$ok) {
            return null;
        }

        $data = json_decode($response, true);
        $entries = $data['data'][(string) $stationId] ?? null;
        if (!is_array($entries) || $entries === []) {
            return null;
        }

        $latestTimestamp = max(array_keys($entries));
        $latest = $entries[$latestTimestamp];
        // [0]=Ende-Zeitstempel, [1]=Gesamtindex 0-4, [2]=unvollständig-Flag, [3..]=je Komponente [id, wert, index, anteil]
        $werte = [];
        foreach (array_slice($latest, 3) as $component) {
            $name = self::COMPONENT_NAMES[$component[0]] ?? null;
            if ($name !== null) {
                $werte[$name] = $component[1];
            }
        }

        return [
            'gemessen_bis' => $latest[0],
            'index' => $latest[1],
            'index_label' => self::INDEX_LABELS[$latest[1]] ?? 'unbekannt',
            'werte' => $werte,
        ];
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
