-- Arbeitslosenquote je Kreis, einmalig importiert (bin/import_unemployment.php)
-- von der API der Statistik der Bundesagentur für Arbeit — analog zu
-- climate_normals/air_stations: Kreisgrenzen und die grobe Wirtschaftslage
-- einer Region ändern sich nicht von Tag zu Tag, ein periodischer Neu-Import
-- reicht.
CREATE TABLE IF NOT EXISTS unemployment_rates (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kreis_name      VARCHAR(100) NOT NULL,
  quote           DECIMAL(4,1) NOT NULL,
  reporting_month VARCHAR(20) NOT NULL,
  imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_kreis (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache für die Zuordnung Kartenmitte -> Kreis. Anders als bei den
-- Klimastationen (dort reicht die nächstgelegene aus unserer eigenen DB)
-- braucht die Kreis-Zuordnung eine echte Reverse-Geocoding-Abfrage bei
-- Nominatim (welcher Verwaltungskreis liegt hier?) — und die unterliegt
-- Nominatims Nutzungsrichtlinie genau wie die Adresssuche, also gecacht statt
-- live pro Kartenbewegung. Koordinaten werden auf 2 Nachkommastellen
-- gerundet (~1km Rasterzelle) — für Kreis-Grenzen (meist zehn(er) km groß)
-- grob genug, um nahegelegene Kartenmitten denselben Cache-Eintrag teilen
-- zu lassen, ohne dass es in relevantem Umfang über eine Kreisgrenze hinweg
-- danebenliegt.
CREATE TABLE IF NOT EXISTS kreis_lookup_cache (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lat_rounded DECIMAL(5,2) NOT NULL,
  lon_rounded DECIMAL(5,2) NOT NULL,
  kreis_name  VARCHAR(100) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_coord (lat_rounded, lon_rounded)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
