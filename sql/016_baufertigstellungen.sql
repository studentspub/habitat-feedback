-- Baufertigstellungen (neue Wohnungen) je Kreis, einmalig importiert
-- (bin/import_baufertigstellungen.php) aus der Regionaldatenbank Deutschland
-- (GENESIS, Statistik 31121, Tabelle 31121-01-02-4). Tatsächlich geliefertes
-- Angebot — Differenz zu Baugenehmigungen zeigt Bau-Rückstand einer Region.
CREATE TABLE IF NOT EXISTS baufertigstellungen (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  anzahl_wohnungen INT UNSIGNED NOT NULL COMMENT 'Fertiggestellte Wohnungen in Wohngebäuden, Jahressumme',
  reporting_year  SMALLINT UNSIGNED NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
