-- Kriminalitätsrate je Kreis, einmalig importiert (bin/import_crime_rates.php)
-- von der Polizeilichen Kriminalstatistik (PKS) des BKA — dieselbe
-- Kreis-Zuordnung wie bei unemployment_rates (siehe kreis_lookup_cache),
-- nur eine zweite Kennzahl für denselben Kreis.
CREATE TABLE IF NOT EXISTS crime_rates (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  cases           INT UNSIGNED NOT NULL,
  haeufigkeitszahl DECIMAL(8,1) NOT NULL COMMENT 'Straftaten je 100.000 Einwohner',
  reporting_year  SMALLINT UNSIGNED NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
