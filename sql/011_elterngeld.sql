-- Elterngeld-Bezieher je Kreis, einmalig importiert (bin/import_elterngeld.php)
-- aus der Regionaldatenbank Deutschland (GENESIS, Statistik 22922, Tabelle
-- 22922-01-01-4). Die Quelle liefert quartalsweise Werte; wir summieren die
-- vier letztverfügbaren Quartale zu einer Jahreszahl, analog zu den anderen
-- Wirtschafts-Kennzahlen (siehe unemployment_rates, crime_rates).
CREATE TABLE IF NOT EXISTS elterngeld_bezieher (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  bezieher_anzahl INT UNSIGNED NOT NULL COMMENT 'Summe der Leistungsbezüge über 4 Quartale',
  reporting_year  SMALLINT UNSIGNED NOT NULL COMMENT 'Jahr des jüngsten einbezogenen Quartals',
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
