<?php

namespace App\Http;

use App\Support\Db;

final class SearchController
{
    /** GET /api/search?q= — Autovervollständigung gegen bereits sichtbare Abschnitte. */
    public function search(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            $this->json(['results' => []]);
            return;
        }

        $config = require __DIR__ . '/../../config/config.php';
        $visibility = $config['visibility'];

        $stmt = Db::get()->prepare(
            'SELECT s.id, s.street_name, s.segment_index, s.city, s.postal_code,
                    s.centroid_lat, s.centroid_lon
             FROM segments s
             JOIN segment_stats st ON st.segment_id = s.id
             WHERE (s.street_name LIKE :q1 OR s.city LIKE :q2)
               AND s.address_count >= :min_addr
               AND st.rating_count >= :min_ratings
             ORDER BY s.street_name, s.segment_index
             LIMIT 15'
        );
        $stmt->execute([
            'q1' => '%' . $q . '%',
            'q2' => '%' . $q . '%',
            'min_addr' => $visibility['min_address_count'],
            'min_ratings' => $visibility['min_rating_count'],
        ]);

        $this->json(['results' => $stmt->fetchAll()]);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
