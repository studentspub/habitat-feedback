-- Wanderungssaldo je Kreis, einmalig importiert (bin/import_wanderungssaldo.php)
-- aus der Regionaldatenbank Deutschland (GENESIS, Statistik 12711, Tabelle
-- 12711-91-01-4). Zuzüge minus Fortzüge über Gemeindegrenzen — Signal für
-- Nachfragedruck auf den Wohnungsmarkt einer Region.
CREATE TABLE IF NOT EXISTS wanderungssaldo (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  zuzuege         INT UNSIGNED NOT NULL,
  fortzuege       INT UNSIGNED NOT NULL,
  saldo           INT NOT NULL COMMENT 'zuzuege - fortzuege, kann negativ sein',
  reporting_year  SMALLINT UNSIGNED NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
