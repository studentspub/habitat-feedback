-- Gewerbean-/-abmeldungen je Kreis, einmalig importiert
-- (bin/import_gewerbeanzeigen.php) aus der Regionaldatenbank Deutschland
-- (GENESIS, Statistik 52311, Tabelle 52311-01-04-4). Saldo aus Anmeldungen
-- minus Abmeldungen als Signal für wirtschaftliche Dynamik/Gründungsklima.
CREATE TABLE IF NOT EXISTS gewerbeanzeigen (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  anmeldungen     INT UNSIGNED NOT NULL,
  abmeldungen     INT UNSIGNED NOT NULL,
  reporting_year  SMALLINT UNSIGNED NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
