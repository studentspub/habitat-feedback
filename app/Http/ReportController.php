<?php

namespace App\Http;

use App\Support\Db;
use App\Support\RateLimit;

final class ReportController
{
    /** POST /api/reports — meldet die Bewertungen eines Abschnitts als fragwürdig. */
    public function submit(): void
    {
        if (RateLimit::tooMany('reports')) {
            http_response_code(429);
            $this->json(['error' => 'Zu viele Meldungen in kurzer Zeit. Bitte später erneut versuchen.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $segmentId = (int) ($input['segment_id'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));

        if ($segmentId <= 0) {
            http_response_code(422);
            $this->json(['error' => 'segment_id fehlt oder ungültig.']);
            return;
        }
        if ($reason === '') {
            http_response_code(422);
            $this->json(['error' => 'Bitte kurz begründen, was an den Bewertungen nicht stimmt.']);
            return;
        }
        if (mb_strlen($reason) > 1000) {
            $reason = mb_substr($reason, 0, 1000);
        }

        $exists = Db::get()->prepare('SELECT 1 FROM segments WHERE id = :id');
        $exists->execute(['id' => $segmentId]);
        if ($exists->fetchColumn() === false) {
            http_response_code(404);
            $this->json(['error' => 'Unbekannter Straßenabschnitt.']);
            return;
        }

        $stmt = Db::get()->prepare('INSERT INTO reports (segment_id, reason) VALUES (:sid, :reason)');
        $stmt->execute(['sid' => $segmentId, 'reason' => $reason]);

        http_response_code(202);
        $this->json(['status' => 'Danke, deine Meldung wird geprüft.']);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
