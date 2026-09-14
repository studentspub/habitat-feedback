-- Empfänger von Grundsicherung im Alter je Kreis, einmalig importiert
-- (bin/import_grundsicherung.php) aus der Regionaldatenbank Deutschland
-- (GENESIS, Statistik 22151, Tabelle 22151-01-02-4). Seit Berichtsjahr 2020
-- sind die Werte aus Datenschutzgründen 5er-gerundet (max. Abweichung ±2).
CREATE TABLE IF NOT EXISTS grundsicherung_alter (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name          VARCHAR(100) NOT NULL,
  empfaenger_altersgrenze INT UNSIGNED NOT NULL COMMENT 'Empfänger "Altersgrenze und älter" (Grundsicherung im Alter, nicht Erwerbsminderung)',
  reporting_year      SMALLINT UNSIGNED NOT NULL,
  imported_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
