<?php

namespace App\Http;

use App\Domain\SegmentRepository;

final class MapApiController
{
    private SegmentRepository $segments;

    public function __construct()
    {
        $this->segments = new SegmentRepository();
    }

    /** GET /api/map?bbox=minLat,minLon,maxLat,maxLon */
    public function pins(): void
    {
        $bbox = explode(',', $_GET['bbox'] ?? '');
        if (count($bbox) !== 4) {
            http_response_code(400);
            $this->json(['error' => 'bbox erwartet: minLat,minLon,maxLat,maxLon']);
            return;
        }
        [$minLat, $minLon, $maxLat, $maxLon] = array_map('floatval', $bbox);

        $pins = $this->segments->visiblePinsInBbox($minLat, $minLon, $maxLat, $maxLon);
        $this->json(['segments' => $pins]);
    }

    /** GET /api/segments/{id} */
    public function detail(int $id): void
    {
        $detail = $this->segments->findVisibleDetail($id);
        if ($detail === null) {
            http_response_code(404);
            $this->json(['error' => 'Abschnitt nicht gefunden oder noch unter der Sichtbarkeitsschwelle']);
            return;
        }
        $this->json($detail);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
