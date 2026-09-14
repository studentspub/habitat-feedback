-- Einfacher IP-Präfix-Zähler für Rate-Limiting auf öffentlichen
-- Schreib-Endpunkten (Bauplan, Sicherheits-Checkliste Abschnitt 07).
-- Kein externer Dienst nötig, alte Zeilen räumt die Anwendung selbst auf.

CREATE TABLE IF NOT EXISTS rate_limit_hits (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_prefix   VARCHAR(50) NOT NULL,
  endpoint    VARCHAR(50) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lookup (ip_prefix, endpoint, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
