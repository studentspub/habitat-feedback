-- Beantragte Insolvenzverfahren (insgesamt, Unternehmen + Privatpersonen) je
-- Kreis, einmalig importiert (bin/import_insolvenzen.php) aus der
-- Regionaldatenbank Deutschland (GENESIS, Statistik 52411, Tabelle
-- 52411-01-01-4). Signal für wirtschaftliche Stabilität der Region.
CREATE TABLE IF NOT EXISTS insolvenzen (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  anzahl          INT UNSIGNED NOT NULL COMMENT 'Beantragte Insolvenzverfahren insgesamt, Jahressumme',
  reporting_year  SMALLINT UNSIGNED NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
