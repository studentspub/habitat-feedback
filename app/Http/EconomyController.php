<?php

namespace App\Http;

use App\Support\Db;
use App\Support\KreisNames;

final class EconomyController
{
    private const NOMINATIM_MIN_INTERVAL_SECONDS = 1.1;

    /**
     * GET /api/economy?lat=&lon= — Kennzahlen des Kreises, in dem die
     * Kartenmitte liegt (Arbeitsmarkt, Kriminalität, Soziales, Bauen &
     * Wohnen, Bevölkerungsbewegung, Wirtschaft). Zweistufig: erst
     * herausfinden, welcher Kreis das überhaupt ist (Reverse-Geocoding bei
     * Nominatim, gecacht — siehe kreis_lookup_cache), dann alle Kennzahlen
     * aus den eigenen, einmalig importierten Tabellen lesen (bin/import_*).
     * Alle teilen sich dieselbe Kreis-Zuordnung, deshalb ein gemeinsamer
     * Endpunkt statt vieler getrennter — jeder weitere Reverse-Geocoding-
     * Aufruf für dieselbe Kartenmitte wäre reine Verschwendung.
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
            $this->json(['kreis' => null]);
            return;
        }

        $db = Db::get();

        $unemployment = $this->fetchOne($db, 'SELECT quote, reporting_month FROM unemployment_rates WHERE kreis_name = :kreis', $kreisName);
        $crime = $this->fetchOne($db, 'SELECT haeufigkeitszahl, reporting_year FROM crime_rates WHERE kreis_name = :kreis', $kreisName);
        $grundsicherung = $this->fetchOne($db, 'SELECT empfaenger_altersgrenze, reporting_year FROM grundsicherung_alter WHERE kreis_name = :kreis', $kreisName);
        $elterngeld = $this->fetchOne($db, 'SELECT bezieher_anzahl, reporting_year FROM elterngeld_bezieher WHERE kreis_name = :kreis', $kreisName);
        $wohnungsbestand = $this->fetchOne($db, 'SELECT anzahl_wohnungen, reporting_year FROM wohnungsbestand WHERE kreis_name = :kreis', $kreisName);
        $einkommen = $this->fetchOne($db, 'SELECT je_einwohner_eur, reporting_year FROM einkommen_haushalte WHERE kreis_name = :kreis', $kreisName);
        $wanderung = $this->fetchOne($db, 'SELECT saldo, reporting_year FROM wanderungssaldo WHERE kreis_name = :kreis', $kreisName);
        $baugenehmigungen = $this->fetchOne($db, 'SELECT anzahl_wohnungen, reporting_year FROM baugenehmigungen WHERE kreis_name = :kreis', $kreisName);
        $baufertigstellungen = $this->fetchOne($db, 'SELECT anzahl_wohnungen, reporting_year FROM baufertigstellungen WHERE kreis_name = :kreis', $kreisName);
        $gewerbeanzeigen = $this->fetchOne($db, 'SELECT anmeldungen, abmeldungen, reporting_year FROM gewerbeanzeigen WHERE kreis_name = :kreis', $kreisName);
        $insolvenzen = $this->fetchOne($db, 'SELECT anzahl, reporting_year FROM insolvenzen WHERE kreis_name = :kreis', $kreisName);

        $this->json([
            'kreis' => $kreisName,
            'arbeitslosenquote' => $unemployment !== null ? [
                'quote' => (float) $unemployment['quote'],
                'monat' => $unemployment['reporting_month'],
            ] : null,
            'kriminalitaet' => $crime !== null ? [
                'haeufigkeitszahl' => (float) $crime['haeufigkeitszahl'],
                'jahr' => (int) $crime['reporting_year'],
            ] : null,
            'grundsicherungImAlter' => $grundsicherung !== null ? [
                'anzahl' => (int) $grundsicherung['empfaenger_altersgrenze'],
                'jahr' => (int) $grundsicherung['reporting_year'],
            ] : null,
            'elterngeld' => $elterngeld !== null ? [
                'anzahl' => (int) $elterngeld['bezieher_anzahl'],
                'jahr' => (int) $elterngeld['reporting_year'],
            ] : null,
            'wohnungsbestand' => $wohnungsbestand !== null ? [
                'anzahl' => (int) $wohnungsbestand['anzahl_wohnungen'],
                'jahr' => (int) $wohnungsbestand['reporting_year'],
            ] : null,
            'einkommen' => $einkommen !== null ? [
                'jeEinwohner' => (int) $einkommen['je_einwohner_eur'],
                'jahr' => (int) $einkommen['reporting_year'],
            ] : null,
            'wanderungssaldo' => $wanderung !== null ? [
                'saldo' => (int) $wanderung['saldo'],
                'jahr' => (int) $wanderung['reporting_year'],
            ] : null,
            'baugenehmigungen' => $baugenehmigungen !== null ? [
                'anzahl' => (int) $baugenehmigungen['anzahl_wohnungen'],
                'jahr' => (int) $baugenehmigungen['reporting_year'],
            ] : null,
            'baufertigstellungen' => $baufertigstellungen !== null ? [
                'anzahl' => (int) $baufertigstellungen['anzahl_wohnungen'],
                'jahr' => (int) $baufertigstellungen['reporting_year'],
            ] : null,
            'gewerbeanzeigen' => $gewerbeanzeigen !== null ? [
                'anmeldungen' => (int) $gewerbeanzeigen['anmeldungen'],
                'abmeldungen' => (int) $gewerbeanzeigen['abmeldungen'],
                'jahr' => (int) $gewerbeanzeigen['reporting_year'],
            ] : null,
            'insolvenzen' => $insolvenzen !== null ? [
                'anzahl' => (int) $insolvenzen['anzahl'],
                'jahr' => (int) $insolvenzen['reporting_year'],
            ] : null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function fetchOne(\PDO $db, string $sql, string $kreisName): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute(['kreis' => $kreisName]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
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
        if (http_response_code() === 200) {
            // Kreis-Kennzahlen werden höchstens monatlich neu importiert; die Karte rundet lat/lon auf 0,01°, damit der Browser-Cache greift (Issue #13).
            header('Cache-Control: public, max-age=86400');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
