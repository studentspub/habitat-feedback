<?php

namespace App\Http;

use App\Support\Db;
use App\Support\GebietLookup;
use App\Support\InkarIndikatoren;

final class RegionController
{
    /**
     * GET /api/region?lat=&lon= — BBSR-INKAR-Kennzahlen (Issue #18) für die
     * Gemeinde und den Kreis der Kartenmitte, je mit Jahr und dem Wert für
     * ganz Deutschland. Gemeinde/Kreis kommen lokal aus den VG250-Grenzen
     * (GebietLookup, Issue #17), die Werte aus inkar_werte (bin/import_inkar.php).
     * Je Gebiet und Indikator der neueste vorhandene Jahrgang.
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

        $gebiet = GebietLookup::fuerPunkt($lat, $lon);
        if ($gebiet === null) {
            $this->json(['gemeinde' => null]);
            return;
        }

        $stmt = Db::get()->prepare(
            'SELECT w.kuerzel, w.jahr, w.wert, i.wert_bund
             FROM inkar_werte w
             JOIN (SELECT ebene, ags, kuerzel, MAX(jahr) AS jahr FROM inkar_werte
                   WHERE (ebene = \'GEM\' AND ags = :gem) OR (ebene = \'KRS\' AND ags = :krs)
                   GROUP BY ebene, ags, kuerzel) neu
               ON neu.ebene = w.ebene AND neu.ags = w.ags AND neu.kuerzel = w.kuerzel AND neu.jahr = w.jahr
             LEFT JOIN inkar_indikatoren i ON i.kuerzel = w.kuerzel'
        );
        $stmt->execute(['gem' => $gebiet['gemeinde_ags'], 'krs' => $gebiet['kreis_ags']]);
        $werte = [];
        foreach ($stmt->fetchAll() as $r) {
            $werte[$r['kuerzel']] = $r;
        }

        $gruppen = [];
        foreach (InkarIndikatoren::GRUPPEN as $key => $titel) {
            $gruppen[$key] = ['key' => $key, 'titel' => $titel, 'werte' => []];
        }
        foreach (InkarIndikatoren::LISTE as $kuerzel => $ind) {
            $w = $werte[$kuerzel] ?? null;
            $gruppen[$ind['gruppe']]['werte'][] = [
                'kuerzel' => $kuerzel,
                'label' => $ind['label'],
                'einheit' => $ind['einheit'],
                'stellen' => $ind['stellen'],
                'besser' => $ind['besser'],
                'info' => $ind['info'],
                'wert' => $w !== null ? (float) $w['wert'] : null,
                'jahr' => $w !== null ? (int) $w['jahr'] : null,
                'deutschland' => $w !== null && $w['wert_bund'] !== null ? (float) $w['wert_bund'] : null,
            ];
        }

        $this->json([
            'gemeinde' => ['name' => $gebiet['gemeinde'], 'ags' => $gebiet['gemeinde_ags']],
            'kreis' => ['name' => $gebiet['kreis'], 'bez' => $gebiet['kreis_bez'], 'ags' => $gebiet['kreis_ags']],
            'gruppen' => array_values($gruppen),
            'quelle' => 'Laufende Raumbeobachtung des BBSR – INKAR, Ausgabe 07/2025 (dl-de/by-2.0)',
        ]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (http_response_code() === 200) {
            // INKAR erscheint einmal im Jahr; die Karte rundet lat/lon auf 0,01° (Issue #13).
            header('Cache-Control: public, max-age=86400');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
