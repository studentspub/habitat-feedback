-- Cache für die Adress-Autovervollständigung im Bewertungsformular.
-- Verhindert, dass jeder Tastendruck eine eigene Nominatim-Anfrage auslöst —
-- sowohl aus Nominatim-Nutzungsbedingungsgründen (max. 1 Anfrage/Sek.) als
-- auch, damit Nutzer-IPs nie direkt bei Nominatim ankommen.
CREATE TABLE IF NOT EXISTS address_autocomplete_cache (
  query_normalized  VARCHAR(190) PRIMARY KEY,
  response_json     JSON NOT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
