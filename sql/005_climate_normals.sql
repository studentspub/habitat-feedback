-- Klimanormalwerte (DWD, Bezugszeitraum 1991-2020) je Station. Reine
-- Referenzdaten — einmalig importiert (bin/import_climate_normals.php),
-- zur Laufzeit nur gelesen, kein Live-Abruf nötig. Getrennte Stationsnetze
-- für Temperatur (dünner) und Niederschlag (dichter), deshalb beide als
-- eigene Spaltengruppen in einer Tabelle statt eines Joins.
CREATE TABLE IF NOT EXISTS climate_normals (
  station_id    INT UNSIGNED PRIMARY KEY,
  station_name  VARCHAR(100) NOT NULL,
  lat           DECIMAL(9,6) NOT NULL,
  lon           DECIMAL(9,6) NOT NULL,
  period        VARCHAR(9) NOT NULL DEFAULT '1991-2020',

  temp_jan DECIMAL(4,1) NULL, temp_feb DECIMAL(4,1) NULL, temp_mar DECIMAL(4,1) NULL,
  temp_apr DECIMAL(4,1) NULL, temp_mai DECIMAL(4,1) NULL, temp_jun DECIMAL(4,1) NULL,
  temp_jul DECIMAL(4,1) NULL, temp_aug DECIMAL(4,1) NULL, temp_sep DECIMAL(4,1) NULL,
  temp_okt DECIMAL(4,1) NULL, temp_nov DECIMAL(4,1) NULL, temp_dez DECIMAL(4,1) NULL,
  temp_jahr DECIMAL(4,1) NULL,

  precip_jan DECIMAL(6,1) NULL, precip_feb DECIMAL(6,1) NULL, precip_mar DECIMAL(6,1) NULL,
  precip_apr DECIMAL(6,1) NULL, precip_mai DECIMAL(6,1) NULL, precip_jun DECIMAL(6,1) NULL,
  precip_jul DECIMAL(6,1) NULL, precip_aug DECIMAL(6,1) NULL, precip_sep DECIMAL(6,1) NULL,
  precip_okt DECIMAL(6,1) NULL, precip_nov DECIMAL(6,1) NULL, precip_dez DECIMAL(6,1) NULL,
  precip_jahr DECIMAL(6,1) NULL,

  INDEX idx_lat_lon (lat, lon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
