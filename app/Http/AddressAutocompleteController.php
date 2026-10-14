<?php

namespace App\Http;

use App\Support\StrassenSuche;

final class AddressAutocompleteController
{
    private const MIN_QUERY_LENGTH = 3;

    /**
     * GET /api/address-autocomplete?q=…[&orte=1] — Vorschläge für Adressfelder
     * aus dem lokalen Straßenverzeichnis (Issue #15, Tabelle `strassen`,
     * bin/import_strassen.php). Kein externer Dienst beim Tippen: Nominatim
     * verbietet Autocomplete über seine API, und Tastenanschläge sollen den
     * Server nicht verlassen. Nominatim fragt nur noch der Geocode-Worker,
     * einmal je abgeschickter Bewertung.
     *
     * Bewertungsformular: nur Straßen ("Kantstraße, 10623 Berlin"); eine
     * mitgetippte Hausnummer kommt separat als `hausnummer` zurück.
     * Kartenfeld „Adresse finden" (Issue #14, orte=1): zusätzlich Gemeinden
     * mit Umriss-Rechteck. Es zeigt keine Bewertungsdaten an und verrät daher
     * nicht, ob eine noch unsichtbare Adresse bewertet wurde.
     */
    public function search(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            $this->json(['results' => [], 'hausnummer' => null]);
            return;
        }
        try {
            $antwort = StrassenSuche::suchen(mb_substr($q, 0, 120), ($_GET['orte'] ?? '') === '1');
        } catch (\PDOException $e) {
            // Tabelle fehlt (Migration 025 nicht eingespielt) — Formular bleibt benutzbar, nur ohne Vorschläge.
            $antwort = ['results' => [], 'hausnummer' => null, 'hinweis' => 'Straßenverzeichnis nicht verfügbar'];
        }
        $this->json($antwort);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!isset($data['hinweis'])) {
            header('Cache-Control: public, max-age=86400');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
