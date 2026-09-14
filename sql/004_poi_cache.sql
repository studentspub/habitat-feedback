-- Cache für POI-Abfragen (Spielplätze, Kneipen, Ärzte, ...) aus OpenStreetMap.
-- Wie bei der Adress-Autovervollständigung: verhindert, dass jeder
-- Kartenschwenk eine eigene Overpass-Anfrage auslöst, und hält Overpass
-- außerhalb dessen Nutzungsrichtlinie (kein häufiges interaktives Abfragen
-- ohne eigenes Caching) fern vom Browser.
CREATE TABLE IF NOT EXISTS poi_cache (
  cache_key         VARCHAR(80) PRIMARY KEY,   -- category + gerundete BBox
  category          VARCHAR(30) NOT NULL,
  response_json     JSON NOT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
