-- Gemeinde- und Kreisgrenzen (BKG VG250, Gebietsstand 31.12.2023), Issue #17.
-- Ersetzt die Nominatim-Rückwärtssuche für "in welchem Kreis liegt die
-- Kartenmitte" und liefert den amtlichen Gemeindeschlüssel (AGS) für
-- Gemeinde-Kennzahlen (INKAR, Issue #18).
--
-- Geometrie in Grad (x = Länge, y = Breite), SRID 0: für Punkt-in-Polygon
-- reicht die ebene Rechnung, und MariaDB kann ohnehin nicht umprojizieren.
-- Befüllt von bin/import_vg250.php. Quellvermerk: © GeoBasis-DE / BKG (2023), dl-de/by-2.0

CREATE TABLE IF NOT EXISTS gebiete_kreis (
    ags        CHAR(5)      NOT NULL PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,              -- VG250 GEN, z.B. "Rhein-Sieg-Kreis"
    bez        VARCHAR(40)  NOT NULL,              -- Kreisfreie Stadt / Landkreis / Kreis / Stadtkreis
    kreis_name VARCHAR(120) NULL,                  -- Schlüssel der bestehenden Kreis-Tabellen (KreisNames)
    geom       MULTIPOLYGON NOT NULL,
    SPATIAL INDEX sp_geom (geom),
    INDEX idx_kreis_name (kreis_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gebiete_gemeinde (
    ags        CHAR(8)      NOT NULL PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,              -- VG250 GEN
    bez        VARCHAR(40)  NOT NULL,              -- Stadt / Gemeinde / Gemeindefreies Gebiet
    kreis_ags  CHAR(5)      NOT NULL,
    geom       MULTIPOLYGON NOT NULL,
    SPATIAL INDEX sp_geom (geom),
    INDEX idx_kreis (kreis_ags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
