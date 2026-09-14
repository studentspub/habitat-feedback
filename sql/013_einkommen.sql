-- Verfügbares Einkommen der privaten Haushalte je Einwohner, je Kreis,
-- einmalig importiert (bin/import_einkommen.php) aus der Regionaldatenbank
-- Deutschland (GENESIS, Statistik 82000, Tabelle 82000-07-01-4). Klassischer
-- Kaufkraft-/Wohlstandsindikator für Regionalvergleiche.
CREATE TABLE IF NOT EXISTS einkommen_haushalte (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  je_einwohner_eur INT UNSIGNED NOT NULL COMMENT 'Verfügbares Einkommen der privaten Haushalte je Einwohner in EUR/Jahr',
  reporting_year  SMALLINT UNSIGNED NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
