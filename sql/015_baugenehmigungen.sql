-- Baugenehmigungen (neue Wohnungen) je Kreis, einmalig importiert
-- (bin/import_baugenehmigungen.php) aus der Regionaldatenbank Deutschland
-- (GENESIS, Statistik 31111, Tabelle 31111-01-02-4). Frühindikator: was in
-- den nächsten 1-2 Jahren an Wohnungsangebot voraussichtlich nachkommt.
CREATE TABLE IF NOT EXISTS baugenehmigungen (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  anzahl_wohnungen INT UNSIGNED NOT NULL COMMENT 'Genehmigte Wohnungen in Wohngebäuden, Jahressumme',
  reporting_year  SMALLINT UNSIGNED NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
