<?php

namespace App\Http;

use App\Support\Db;
use App\Support\Fingerprint;
use App\Support\RateLimit;

final class RatingController
{
    private const CRITERIA = ['ruhe', 'instandhaltung', 'umgangston', 'infrastruktur', 'sicherheit'];

    /** POST /api/ratings — nimmt Rohadresse + 5 Scores entgegen, stellt in die Geocode-Warteschlange. */
    public function submit(): void
    {
        if (RateLimit::tooMany('ratings')) {
            http_response_code(429);
            $this->json(['error' => 'Zu viele Bewertungen in kurzer Zeit. Bitte später erneut versuchen.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $rawAddress = trim((string) ($input['address'] ?? ''));
        if ($rawAddress === '') {
            $this->fail(422, 'Adresse fehlt.');
            return;
        }

        $scores = [];
        foreach (self::CRITERIA as $key) {
            $value = $input['scores'][$key] ?? null;
            if (!is_int($value) && !is_numeric($value)) {
                $this->fail(422, "Score für '$key' fehlt oder ist ungültig.");
                return;
            }
            $value = (int) $value;
            if ($value < 1 || $value > 5) {
                $this->fail(422, "Score für '$key' muss zwischen 1 und 5 liegen.");
                return;
            }
            $scores[$key] = $value;
        }

        $payload = [
            'scores' => $scores,
            'fingerprint_raw' => Fingerprint::rawSignal(),
        ];

        $stmt = Db::get()->prepare(
            'INSERT INTO geocode_queue (raw_address, payload_json) VALUES (:addr, :payload)'
        );
        $stmt->execute([
            'addr' => $rawAddress,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        http_response_code(202);
        $this->json([
            'status' => 'wird verarbeitet',
            'hinweis' => 'Die Bewertung erscheint auf der Karte, sobald die Adresse zugeordnet ist und der Abschnitt die Sichtbarkeitsschwelle erreicht.',
        ]);
    }

    private function fail(int $code, string $message): void
    {
        http_response_code($code);
        $this->json(['error' => $message]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
