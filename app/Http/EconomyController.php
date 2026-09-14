<?php

namespace App\Http;

use App\Support\Db;
use App\Support\KreisNames;

final class EconomyController
{
    private const NOMINATIM_MIN_INTERVAL_SECONDS = 1.1;

    /**
     * GET /api/economy?lat=&lon= — Arbeitslosenquote des Kreises, in dem die
     * Kartenmitte liegt. Zweistufig: erst herausfinden, welcher Kreis das
     * überhaupt ist (Reverse-Geocoding bei Nominatim, gecacht — siehe
     * kreis_lookup_cache), dann die Quote aus der eigenen, einmalig
     * importierten unemployment_rates-Tabelle lesen (bin/import_unemployment.php).
     * Anders als bei Klima/Luft gibt es keinen fertigen Datensatz "nächste
     * Station" — die Kreis-Zugehörigkeit selbst muss erst ermittelt werden.
     */
    public function forLocation(): void
    {
        $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
        $lon = isset($_GET['lon']) ? (float) $_GET['lon'] : null;
        if ($lat === null || $lon === null) {
            http_response_code(422);
            $this->json(['error' => 'lat und lon erforderlich.']);
            return;
        }

        $kreisName = $this->resolveKreis($lat, $lon);
        if ($kreisName === null) {
            $this->json(['quote' => null]);
            return;
        }

        $stmt = Db::get()->prepare(
            'SELECT quote, reporting_month FROM unemployment_rates WHERE kreis_name = :kreis'
        );
        $stmt->execute(['kreis' => $kreisName]);
        $row = $stmt->fetch();
        if ($row === false) {
            // Kreis erkannt, aber noch nicht (oder nicht mehr) importiert —
            // z.B. während der Import noch läuft.
            $this->json(['quote' => null, 'kreis' => $kreisName]);
            return;
        }

        $this->json([
            'kreis' => $kreisName,
            'quote' => (float) $row['quote'],
            'monat' => $row['reporting_month'],
        ]);
    }

    private function resolveKreis(float $lat, float $lon): ?string
    {
        // Auf ~1km gerundet, siehe Kommentar in sql/008_unemployment.sql —
        // Kreis-Grenzen sind grob genug, dass benachbarte Kartenmitten sich
        // denselben Cache-Eintrag (und damit dieselbe Nominatim-Anfrage)
        // teilen können.
        $latRounded = round($lat, 2);
        $lonRounded = round($lon, 2);

        $db = Db::get();
        $cached = $db->prepare(
            'SELECT kreis_name FROM kreis_lookup_cache WHERE lat_rounded = :lat AND lon_rounded = :lon'
        );
        $cached->execute(['lat' => $latRounded, 'lon' => $lonRounded]);
        $hit = $cached->fetch();
        if ($hit !== false) {
            return $hit['kreis_name']; // kann auch NULL sein (schon mal erfolglos versucht)
        }

        // Kein Cache-Treffer — genau wie bei der Adresssuche nur live bei
        // Nominatim nachfragen, wenn seit der letzten eigenen Anfrage genug
        // Zeit vergangen ist (Nominatims Nutzungsrichtlinie).
        $lastCall = $db->query('SELECT MAX(created_at) FROM kreis_lookup_cache')->fetchColumn();
        if ($lastCall !== null && (time() - strtotime($lastCall)) < self::NOMINATIM_MIN_INTERVAL_SECONDS) {
            return null; // nächster Kartenschwenk versucht es erneut
        }

        $address = $this->reverseGeocode($lat, $lon);
        $kreisName = $address !== null
            ? KreisNames::match($address['county'] ?? null, $address['city'] ?? $address['town'] ?? null)
            : null;

        $db->prepare(
            'INSERT INTO kreis_lookup_cache (lat_rounded, lon_rounded, kreis_name) VALUES (:lat, :lon, :kreis)
             ON DUPLICATE KEY UPDATE kreis_name = VALUES(kreis_name), created_at = NOW()'
        )->execute(['lat' => $latRounded, 'lon' => $lonRounded, 'kreis' => $kreisName]);

        return $kreisName;
    }

    /** @return array<string, string>|null */
    private function reverseGeocode(float $lat, float $lon): ?array
    {
        // Siehe worker/geocode_worker.py: Platzhalter-Kontakt im User-Agent
        // wird von Nominatim mit HTTP 403 geblockt, vor Live-Betrieb echten
        // eintragen. https://operations.osmfoundation.org/policies/nominatim/
        $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
            'lat' => $lat, 'lon' => $lon, 'format' => 'jsonv2', 'addressdetails' => 1,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['User-Agent: habitat-feedback-webapp/1.0 (dev)'],
            CURLOPT_TIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        $ok = $response !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        if (!$ok) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['address'] ?? null;
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
