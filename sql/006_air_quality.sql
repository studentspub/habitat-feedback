-- Aktive Luftmess-Stationen des Umweltbundesamts. Wie climate_normals:
-- einmalig importiert (bin/import_air_stations.php), Stationsstandorte
-- ändern sich selten. Die eigentlichen Messwerte kommen dagegen live aus
-- der UBA-API, deshalb zusätzlich ein kurzlebiger Cache dafür.
CREATE TABLE IF NOT EXISTS air_stations (
  station_id    INT UNSIGNED PRIMARY KEY,
  station_name  VARCHAR(150) NOT NULL,
  city          VARCHAR(100) NOT NULL,
  lat           DECIMAL(9,6) NOT NULL,
  lon           DECIMAL(9,6) NOT NULL,
  INDEX idx_lat_lon (lat, lon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aktuelle Messwerte je Station — kurzlebig (Stundenwerte, siehe
-- AirQualityController), verhindert nur, dass mehrere Anfragen in kurzer
-- Folge dieselbe Station erneut bei der UBA abfragen.
CREATE TABLE IF NOT EXISTS air_quality_cache (
  station_id     INT UNSIGNED PRIMARY KEY,
  response_json  JSON NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
