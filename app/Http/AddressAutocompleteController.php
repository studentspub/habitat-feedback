<?php

namespace App\Http;

use App\Support\Db;

final class AddressAutocompleteController
{
    private const MIN_QUERY_LENGTH = 4;
    private const CACHE_LIFETIME_DAYS = 30;
    private const NOMINATIM_MIN_INTERVAL_SECONDS = 1.1;

    /**
     * GET /api/address-autocomplete?q=… — Vorschläge für das Adressfeld im
     * Bewertungsformular. Geht NIE direkt aus dem Browser gegen Nominatim,
     * sondern immer über diesen serverseitigen Proxy mit Cache, damit weder
     * Nominatims Ratenlimit noch dessen Nutzungsbedingungen verletzt werden
     * und Nutzer-IPs nicht direkt bei Nominatim ankommen.
     */
    public function search(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            $this->json(['results' => []]);
            return;
        }

        $normalized = mb_strtolower($q);
        $db = Db::get();

        $cached = $db->prepare(
            'SELECT response_json FROM address_autocomplete_cache
             WHERE query_normalized = :q AND created_at >= :cutoff'
        );
        $cached->execute([
            'q' => $normalized,
            'cutoff' => date('Y-m-d H:i:s', time() - self::CACHE_LIFETIME_DAYS * 86400),
        ]);
        $hit = $cached->fetchColumn();
        if ($hit !== false) {
            $this->json(['results' => json_decode($hit, true)]);
            return;
        }

        // Kein Treffer im Cache — nur live bei Nominatim nachfragen, wenn
        // seit der letzten eigenen Anfrage genug Zeit vergangen ist. Sonst
        // lieber leer antworten (der nächste Tastendruck/Debounce holt es
        // nach) als Nominatims Ratenlimit zu riskieren.
        $lastCall = $db->query('SELECT MAX(created_at) FROM address_autocomplete_cache')->fetchColumn();
        if ($lastCall !== null && (time() - strtotime($lastCall)) < self::NOMINATIM_MIN_INTERVAL_SECONDS) {
            $this->json(['results' => [], 'hinweis' => 'kurz warten']);
            return;
        }

        $results = $this->fetchFromNominatim($q);

        $store = $db->prepare(
            'INSERT INTO address_autocomplete_cache (query_normalized, response_json)
             VALUES (:q, :json)
             ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), created_at = NOW()'
        );
        $store->execute(['q' => $normalized, 'json' => json_encode($results, JSON_UNESCAPED_UNICODE)]);

        $this->json(['results' => $results]);
    }

    /** @return array<int, array{label:string}> */
    private function fetchFromNominatim(string $query): array
    {
        // Siehe worker/geocode_worker.py für denselben Hinweis: ein
        // Platzhalter-Kontakt wie "example.invalid" wird von Nominatim
        // aktiv mit HTTP 403 geblockt — vor Live-Betrieb hier echten
        // Kontakt eintragen. https://operations.osmfoundation.org/policies/nominatim/
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $query,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 5,
            'countrycodes' => 'de',
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
            return [];
        }

        $data = json_decode($response, true) ?? [];
        return array_map(
            static fn (array $r): array => ['label' => $r['display_name']],
            $data
        );
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
