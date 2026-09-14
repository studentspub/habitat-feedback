-- Testdaten für die lokale Entwicklung — NICHT für Produktion.
-- Deckt bewusst alle vier Schwellen-Kombinationen ab (siehe Konzeptpapier Abschnitt 02):
--   1) genug Adressen + genug Bewertungen  -> sichtbar
--   2) zu wenig Adressen, genug Bewertungen -> unsichtbar
--   3) genug Adressen, genau an der Bewertungsschwelle -> sichtbar
--   4) genug Adressen, zu wenig Bewertungen -> unsichtbar

INSERT INTO segments (street_name, segment_index, postal_code, city, centroid_lat, centroid_lon, address_count, address_count_at)
VALUES
  ('Musterstraße', 1, '04109', 'Leipzig', 51.339700, 12.373100, 8, NOW()),
  ('Musterstraße', 2, '04109', 'Leipzig', 51.340200, 12.374400, 5, NOW()),
  ('Beispielweg',  1, '10115', 'Berlin',  52.532000, 13.384900, 10, NOW()),
  ('Teststraße',   1, '80331', 'München', 48.137200, 11.575500, 12, NOW())
ON DUPLICATE KEY UPDATE address_count = VALUES(address_count);

-- Musterstraße Abschnitt 1: 4 Bewertungen, überwiegend gut
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 4, 4, 5, 4, 4, SHA2(CONCAT('seed-1-a-', id), 256) FROM segments WHERE street_name='Musterstraße' AND segment_index=1 AND postal_code='04109';
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 3, 4, 4, 5, 4, SHA2(CONCAT('seed-1-b-', id), 256) FROM segments WHERE street_name='Musterstraße' AND segment_index=1 AND postal_code='04109';
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 5, 3, 5, 4, 5, SHA2(CONCAT('seed-1-c-', id), 256) FROM segments WHERE street_name='Musterstraße' AND segment_index=1 AND postal_code='04109';
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 4, 4, 4, 4, 3, SHA2(CONCAT('seed-1-d-', id), 256) FROM segments WHERE street_name='Musterstraße' AND segment_index=1 AND postal_code='04109';

-- Musterstraße Abschnitt 2: 3 Bewertungen, aber nur 5 Adressen -> bleibt unsichtbar
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 2, 2, 3, 2, 2, SHA2(CONCAT('seed-2-a-', id), 256) FROM segments WHERE street_name='Musterstraße' AND segment_index=2 AND postal_code='04109';
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 3, 2, 3, 3, 2, SHA2(CONCAT('seed-2-b-', id), 256) FROM segments WHERE street_name='Musterstraße' AND segment_index=2 AND postal_code='04109';
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 2, 3, 2, 2, 3, SHA2(CONCAT('seed-2-c-', id), 256) FROM segments WHERE street_name='Musterstraße' AND segment_index=2 AND postal_code='04109';

-- Beispielweg: genau 3 Bewertungen (Schwelle exakt erreicht) -> sichtbar
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 5, 5, 4, 5, 5, SHA2(CONCAT('seed-3-a-', id), 256) FROM segments WHERE street_name='Beispielweg' AND segment_index=1 AND postal_code='10115';
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 4, 5, 5, 4, 4, SHA2(CONCAT('seed-3-b-', id), 256) FROM segments WHERE street_name='Beispielweg' AND segment_index=1 AND postal_code='10115';
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 5, 4, 5, 5, 4, SHA2(CONCAT('seed-3-c-', id), 256) FROM segments WHERE street_name='Beispielweg' AND segment_index=1 AND postal_code='10115';

-- Teststraße: nur 1 Bewertung -> bleibt unsichtbar
INSERT INTO ratings (segment_id, score_ruhe, score_instandhaltung, score_umgangston, score_infrastruktur, score_sicherheit, fingerprint_hash)
SELECT id, 3, 3, 3, 3, 3, SHA2(CONCAT('seed-4-a-', id), 256) FROM segments WHERE street_name='Teststraße' AND segment_index=1 AND postal_code='80331';

-- segment_stats für alle vier Abschnitte neu berechnen
INSERT INTO segment_stats (segment_id, rating_count, avg_ruhe, avg_instandhaltung, avg_umgangston, avg_infrastruktur, avg_sicherheit, avg_overall)
SELECT
  s.id,
  COUNT(r.id),
  AVG(r.score_ruhe), AVG(r.score_instandhaltung), AVG(r.score_umgangston), AVG(r.score_infrastruktur), AVG(r.score_sicherheit),
  (AVG(r.score_ruhe) + AVG(r.score_instandhaltung) + AVG(r.score_umgangston) + AVG(r.score_infrastruktur) + AVG(r.score_sicherheit)) / 5
FROM segments s
JOIN ratings r ON r.segment_id = s.id
GROUP BY s.id
ON DUPLICATE KEY UPDATE
  rating_count = VALUES(rating_count),
  avg_ruhe = VALUES(avg_ruhe), avg_instandhaltung = VALUES(avg_instandhaltung),
  avg_umgangston = VALUES(avg_umgangston), avg_infrastruktur = VALUES(avg_infrastruktur),
  avg_sicherheit = VALUES(avg_sicherheit), avg_overall = VALUES(avg_overall);
