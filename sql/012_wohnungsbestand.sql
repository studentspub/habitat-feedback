-- Wohnungsbestand je Kreis, einmalig importiert (bin/import_wohnungsbestand.php)
-- aus der Regionaldatenbank Deutschland (GENESIS, Statistik 31231, Tabelle
-- 31231-02-01-4). Direktester Angebots-Indikator für die Wohnlagen-Bewertung.
CREATE TABLE IF NOT EXISTS wohnungsbestand (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  anzahl_wohnungen INT UNSIGNED NOT NULL COMMENT 'Wohnungen in Wohn- und Nichtwohngebäuden, Stichtag 31.12.',
  reporting_year  SMALLINT UNSIGNED NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
