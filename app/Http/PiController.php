<?php

namespace App\Http;

use App\Domain\SegmentRepository;
use App\Support\Auth;
use App\Support\Db;
use App\Support\Fingerprint;
use PDOException;

/** Nur vom Geocode-Worker angesprochen (Raspberry Pi, später) — Bearer-Token-geschützt. */
final class PiController
{
    /** GET /api/pi/queue — offene Adressen zur Geokodierung. */
    public function queue(): void
    {
        if (!Auth::checkPiToken()) {
            http_response_code(401);
            $this->json(['error' => 'Ungültiger oder fehlender Token.']);
            return;
        }

        $stmt = Db::get()->query(
            "SELECT id, raw_address, payload_json FROM geocode_queue
             WHERE status = 'pending' ORDER BY submitted_at ASC LIMIT 20"
        );
        $items = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'raw_address' => $row['raw_address'],
                'payload' => json_decode($row['payload_json'], true),
            ];
        }, $stmt->fetchAll());

        $this->json(['items' => $items]);
    }

    /** POST /api/pi/resolve — aufgelöster Abschnitt + Scores, Warteschlangen-Eintrag wird abgeschlossen. */
    public function resolve(): void
    {
        if (!Auth::checkPiToken()) {
            http_response_code(401);
            $this->json(['error' => 'Ungültiger oder fehlender Token.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $required = ['queue_id', 'street_name', 'segment_index', 'postal_code', 'city', 'centroid_lat', 'centroid_lon', 'address_count', 'scores', 'fingerprint_raw'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $input)) {
                http_response_code(422);
                $this->json(['error' => "Feld '$key' fehlt."]);
                return;
            }
        }

        $db = Db::get();
        $db->beginTransaction();
        try {
            // Abschnitt finden oder anlegen; Adresszahl/Position bei jedem Lauf auffrischen.
            $upsertSegment = $db->prepare(
                'INSERT INTO segments (street_name, segment_index, postal_code, city, centroid_lat, centroid_lon, address_count, address_count_at)
                 VALUES (:street, :idx, :plz, :city, :lat, :lon, :addrcount, NOW())
                 ON DUPLICATE KEY UPDATE
                    centroid_lat = VALUES(centroid_lat),
                    centroid_lon = VALUES(centroid_lon),
                    address_count = VALUES(address_count),
                    address_count_at = VALUES(address_count_at)'
            );
            $upsertSegment->execute([
                'street' => $input['street_name'],
                'idx' => (int) $input['segment_index'],
                'plz' => $input['postal_code'],
                'city' => $input['city'],
                'lat' => $input['centroid_lat'],
                'lon' => $input['centroid_lon'],
                'addrcount' => (int) $input['address_count'],
            ]);

            $segmentId = (int) $db->query(
                'SELECT id FROM segments WHERE street_name = ' . $db->quote($input['street_name']) .
                ' AND segment_index = ' . (int) $input['segment_index'] .
                ' AND postal_code = ' . $db->quote($input['postal_code'])
            )->fetchColumn();

            $fingerprintHash = Fingerprint::finalHash($segmentId, $input['fingerprint_raw']);
            $scores = $input['scores'];

            $insertRating = $db->prepare(
                'INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
                 VALUES (:sid, :ruhe, :instand, :umgang, :infra, :sicher, :fp)'
            );
            $insertRating->execute([
                'sid' => $segmentId,
                'ruhe' => $scores['ruhe'],
                'instand' => $scores['instandhaltung'],
                'umgang' => $scores['umgangston'],
                'infra' => $scores['infrastruktur'],
                'sicher' => $scores['sicherheit'],
                'fp' => $fingerprintHash,
            ]);

            (new SegmentRepository())->recomputeStats($segmentId);

            $db->prepare('DELETE FROM geocode_queue WHERE id = :id')->execute(['id' => $input['queue_id']]);

            $db->commit();
            $this->json(['status' => 'ok', 'segment_id' => $segmentId]);
        } catch (PDOException $e) {
            $db->rollBack();
            if ($e->getCode() === '23000') {
                // Unique-Constraint (segment_id, fingerprint_hash) — bereits bewertet.
                // Warteschlangen-Eintrag trotzdem entfernen, sonst hängt er ewig fest.
                Db::get()->prepare('DELETE FROM geocode_queue WHERE id = :id')->execute(['id' => $input['queue_id']]);
                http_response_code(409);
                $this->json(['error' => 'Dieses Gerät hat diesen Abschnitt bereits bewertet.']);
                return;
            }
            http_response_code(500);
            $this->json(['error' => 'Interner Fehler beim Verarbeiten.']);
        }
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
