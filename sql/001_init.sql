-- Habitat Feedback: Grundschema
-- Kleinste sichtbare Einheit ist der Straßenabschnitt, nicht die Hausnummer.
-- Siehe Konzeptpapier Abschnitt 02 für die Begründung der Schwellenwerte.

CREATE TABLE IF NOT EXISTS segments (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  street_name        VARCHAR(160) NOT NULL,
  segment_index      SMALLINT NOT NULL,        -- Hausnr.-Zehnerblock (Phase 1); später ggf. OSM-Way-Index
  postal_code        CHAR(5) NOT NULL,
  city               VARCHAR(120) NOT NULL,
  centroid_lat       DECIMAL(9,6) NOT NULL,
  centroid_lon       DECIMAL(9,6) NOT NULL,
  address_count      SMALLINT NOT NULL DEFAULT 0,   -- aus Overpass (Anzahl Adresspunkte im Abschnitt)
  address_count_at   DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_segment (street_name, segment_index, postal_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ratings (
  id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  segment_id             BIGINT UNSIGNED NOT NULL,
  score_ruhe             TINYINT NOT NULL,   -- 1..5
  score_instandhaltung   TINYINT NOT NULL,
  score_umgangston       TINYINT NOT NULL,
  score_infrastruktur    TINYINT NOT NULL,
  score_sicherheit       TINYINT NOT NULL,
  fingerprint_hash       CHAR(64) NOT NULL,   -- SHA-256(salt+segment_id+geräte-signal)
  verified               TINYINT(1) NOT NULL DEFAULT 0,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fingerprint_segment (segment_id, fingerprint_hash),
  CONSTRAINT fk_ratings_segment FOREIGN KEY (segment_id) REFERENCES segments(id),
  CONSTRAINT chk_scores CHECK (
    score_ruhe BETWEEN 1 AND 5 AND
    score_instandhaltung BETWEEN 1 AND 5 AND
    score_umgangston BETWEEN 1 AND 5 AND
    score_infrastruktur BETWEEN 1 AND 5 AND
    score_sicherheit BETWEEN 1 AND 5
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rohe Adresse wartet hier auf Geokodierung durch den Pi-Worker (Phase 2).
-- Wichtig: die Hausnummer wird NIE in `segments`/`ratings` übernommen,
-- sondern nur hier transient bis zur Verarbeitung gehalten.
CREATE TABLE IF NOT EXISTS geocode_queue (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raw_address        VARCHAR(255) NOT NULL,
  payload_json       JSON NOT NULL,          -- die 5 Scores + Geräte-Rohsignal
  status             ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
  attempts           TINYINT NOT NULL DEFAULT 0,
  submitted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at       DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rating_id          BIGINT UNSIGNED NOT NULL,
  reason             VARCHAR(255) NOT NULL,
  status             ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_rating FOREIGN KEY (rating_id) REFERENCES ratings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vorberechnete Aggregate, damit die Karte nicht bei jedem Aufruf
-- über alle ratings mitteln muss. Wird von der Anwendung bei jedem
-- Insert in `ratings` neu berechnet (siehe app/Domain/SegmentStats.php).
CREATE TABLE IF NOT EXISTS segment_stats (
  segment_id          BIGINT UNSIGNED PRIMARY KEY,
  rating_count        INT NOT NULL DEFAULT 0,
  avg_ruhe            DECIMAL(3,2) NULL,
  avg_instandhaltung  DECIMAL(3,2) NULL,
  avg_umgangston      DECIMAL(3,2) NULL,
  avg_infrastruktur   DECIMAL(3,2) NULL,
  avg_sicherheit      DECIMAL(3,2) NULL,
  avg_overall         DECIMAL(3,2) NULL,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_stats_segment FOREIGN KEY (segment_id) REFERENCES segments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
