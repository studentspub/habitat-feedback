-- Ersetzt den bisherigen Live-Overpass-Ansatz (poi_cache) durch einmalig
-- importierte Standorte, genau wie climate_normals/air_stations: Spielplätze,
-- Kneipen usw. ändern sich über Jahre kaum, ein Live-Abruf bei jedem
-- Kartenschwenk macht die Karte nur von einem fremden, öffentlichen Dienst
-- abhängig, ohne dass die Daten dadurch aktueller würden.
CREATE TABLE IF NOT EXISTS poi_locations (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category     VARCHAR(30) NOT NULL,
  osm_id       BIGINT NOT NULL,
  osm_type     ENUM('node', 'way') NOT NULL,
  lat          DECIMAL(9,6) NOT NULL,
  lon          DECIMAL(9,6) NOT NULL,
  name         VARCHAR(200) NULL,
  imported_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_osm_element (category, osm_type, osm_id),
  INDEX idx_category_lat_lon (category, lat, lon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Der bisherige Live-Anfrage-Cache wird nicht mehr gebraucht.
DROP TABLE IF EXISTS poi_cache;
