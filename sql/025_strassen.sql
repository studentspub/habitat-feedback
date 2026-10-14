-- Straßenverzeichnis für die Adress-Autovervollständigung (Issue #15). Befüllt von bin/import_strassen.php.
-- Quelle: OpenStreetMap (© OpenStreetMap-Mitwirkende, ODbL), Geofabrik-Extrakt Deutschland.
-- Eine Zeile je Straße + PLZ + Ort, ohne Hausnummern. Ersetzt die Live-Abfrage bei Nominatim beim Tippen
-- (Nutzungsrichtlinie: kein Autocomplete über die öffentliche API); Nominatim nur noch beim Absenden.

CREATE TABLE IF NOT EXISTS strassen (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150)  NOT NULL,                              -- Schreibweise aus OSM, z.B. "Karl-Marx-Straße"
    name_norm     VARCHAR(150)  CHARACTER SET ascii COLLATE ascii_bin NOT NULL,  -- StrassenSuche::norm(), z.B. "karlmarxstr"
    plz           CHAR(5)       NULL,
    ort           VARCHAR(100)  NOT NULL,                              -- addr:city der Adressen, sonst Gemeinde (VG250)
    ort_norm      VARCHAR(100)  CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    gemeinde_ags  CHAR(8)       NULL,                                  -- amtlicher Gemeindeschlüssel (VG250), für spätere Verknüpfungen
    lat           DECIMAL(7,5)  NOT NULL,                              -- Punkt an der Straße (Adresse nahe der Mitte bzw. Straßenmitte)
    lon           DECIMAL(7,5)  NOT NULL,
    adressen      INT UNSIGNED  NOT NULL DEFAULT 0,                    -- Adresspunkte in OSM, für die Reihenfolge der Vorschläge
    -- Deckt Filter (Name-Anfang, Ort, PLZ) und Sortierung ab: kurze Anfänge wie "ams" treffen
    -- zehntausende Zeilen, die dann nur im Index sortiert werden statt als ganze Zeilen.
    INDEX idx_suche (name_norm, adressen, ort_norm, plz),
    INDEX idx_gemeinde (gemeinde_ags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS strassen_stand (
    id          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    osm_stand   DATE         NOT NULL,            -- Datenstand des OSM-Extrakts
    quelle      VARCHAR(120) NOT NULL,
    importiert  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ortssuche im Kartenfeld „Adresse finden“ (StrassenSuche::orte, name LIKE 'Leip%'): ohne Index
-- liest jede Anfrage alle ~11.000 Gemeinden (40-60 ms), mit Index ~1 ms. IF NOT EXISTS: MariaDB.
CREATE INDEX IF NOT EXISTS idx_name ON gebiete_gemeinde (name);
