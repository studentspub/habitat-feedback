-- ÖPNV-Takt je Haltestelle aus dem GTFS-Feed Deutschland (Issue #24). Befüllt von bin/import_gtfs.php.
-- Quelle: GTFS.DE "Deutschland komplett" (Datengrundlage DELFI e.V.), Creative Commons 4.0.
-- Eine Zeile je Station: Bahnsteige/Masten mit gemeinsamer parent_station werden zusammengefasst,
-- gleichnamige Masten ohne Station im Umkreis von ~150 m ebenso (beide Fahrtrichtungen einer Bushaltestelle).

CREATE TABLE IF NOT EXISTS oepnv_haltestellen (
    id              VARCHAR(80)  NOT NULL PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    lat             DOUBLE       NOT NULL,
    lon             DOUBLE       NOT NULL,
    pt              POINT        NOT NULL,       -- x = lon, y = lat (SRID 0)
    abfahrten       INT          NOT NULL,       -- Abfahrten am Stichtag (Werktag), alle Linien und Richtungen
    abfahrten_6_20  INT          NOT NULL,       -- davon zwischen 6 und 20 Uhr
    verkehrsmittel  VARCHAR(80)  NOT NULL,       -- z.B. "Bus, Tram, S-Bahn"
    SPATIAL INDEX sp_pt (pt),
    INDEX idx_abfahrten (abfahrten)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oepnv_stand (
    id          TINYINT     NOT NULL PRIMARY KEY DEFAULT 1,
    stichtag    DATE        NOT NULL,            -- ausgewerteter Werktag
    feed_datei  VARCHAR(120) NOT NULL,
    importiert  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
