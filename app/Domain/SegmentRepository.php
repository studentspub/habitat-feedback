<?php

namespace App\Domain;

use App\Support\Db;
use PDO;

final class SegmentRepository
{
    /** @var array{min_address_count:int,min_rating_count:int} */
    private array $visibility;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/config.php';
        $this->visibility = $config['visibility'];
    }

    /**
     * Segmente im Kartenausschnitt, die die Sichtbarkeitsschwellen erreichen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visiblePinsInBbox(float $minLat, float $minLon, float $maxLat, float $maxLon): array
    {
        $stmt = Db::get()->prepare(
            'SELECT s.id, s.street_name, s.segment_index, s.city, s.postal_code,
                    s.centroid_lat, s.centroid_lon, s.address_count,
                    st.rating_count, st.avg_overall
             FROM segments s
             JOIN segment_stats st ON st.segment_id = s.id
             WHERE s.centroid_lat BETWEEN :min_lat AND :max_lat
               AND s.centroid_lon BETWEEN :min_lon AND :max_lon
               AND s.address_count >= :min_addr
               AND st.rating_count >= :min_ratings'
        );
        $stmt->execute([
            'min_lat' => $minLat,
            'max_lat' => $maxLat,
            'min_lon' => $minLon,
            'max_lon' => $maxLon,
            'min_addr' => $this->visibility['min_address_count'],
            'min_ratings' => $this->visibility['min_rating_count'],
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Detaildaten eines Abschnitts — nur wenn die Schwellen erreicht sind.
     *
     * @return array<string, mixed>|null
     */
    public function findVisibleDetail(int $segmentId): ?array
    {
        $stmt = Db::get()->prepare(
            'SELECT s.id, s.street_name, s.segment_index, s.city, s.postal_code,
                    s.centroid_lat, s.centroid_lon, s.address_count,
                    st.rating_count, st.avg_ruhe, st.avg_instandhaltung,
                    st.avg_umgangston, st.avg_infrastruktur, st.avg_sicherheit, st.avg_overall
             FROM segments s
             JOIN segment_stats st ON st.segment_id = s.id
             WHERE s.id = :id
               AND s.address_count >= :min_addr
               AND st.rating_count >= :min_ratings'
        );
        $stmt->execute([
            'id' => $segmentId,
            'min_addr' => $this->visibility['min_address_count'],
            'min_ratings' => $this->visibility['min_rating_count'],
        ]);

        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Berechnet segment_stats für einen Abschnitt aus den vorhandenen ratings neu.
     * Wird nach jedem neuen Rating aufgerufen (siehe RatingController).
     */
    public function recomputeStats(int $segmentId): void
    {
        $db = Db::get();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS n,
                    AVG(score_ruhe) AS avg_ruhe,
                    AVG(score_instandhaltung) AS avg_instandhaltung,
                    AVG(score_umgangston) AS avg_umgangston,
                    AVG(score_infrastruktur) AS avg_infrastruktur,
                    AVG(score_sicherheit) AS avg_sicherheit
             FROM ratings WHERE segment_id = :id'
        );
        $stmt->execute(['id' => $segmentId]);
        $row = $stmt->fetch();

        $n = (int) $row['n'];
        $avgOverall = $n > 0
            ? array_sum([
                (float) $row['avg_ruhe'],
                (float) $row['avg_instandhaltung'],
                (float) $row['avg_umgangston'],
                (float) $row['avg_infrastruktur'],
                (float) $row['avg_sicherheit'],
            ]) / 5
            : null;

        $upsert = $db->prepare(
            'INSERT INTO segment_stats
                (segment_id, rating_count, avg_ruhe, avg_instandhaltung, avg_umgangston, avg_infrastruktur, avg_sicherheit, avg_overall)
             VALUES (:id, :n, :ruhe, :instand, :umgang, :infra, :sicher, :overall)
             ON DUPLICATE KEY UPDATE
                rating_count = VALUES(rating_count),
                avg_ruhe = VALUES(avg_ruhe),
                avg_instandhaltung = VALUES(avg_instandhaltung),
                avg_umgangston = VALUES(avg_umgangston),
                avg_infrastruktur = VALUES(avg_infrastruktur),
                avg_sicherheit = VALUES(avg_sicherheit),
                avg_overall = VALUES(avg_overall)'
        );
        $upsert->execute([
            'id' => $segmentId,
            'n' => $n,
            'ruhe' => $row['avg_ruhe'],
            'instand' => $row['avg_instandhaltung'],
            'umgang' => $row['avg_umgangston'],
            'infra' => $row['avg_infrastruktur'],
            'sicher' => $row['avg_sicherheit'],
            'overall' => $avgOverall,
        ]);
    }
}
