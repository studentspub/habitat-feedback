-- DWD-Klimaraster 1 km (multi-annual, Jahreswerte), Issue #19. Befüllt von bin/import_dwd_raster.php.
-- Je Rasterzelle die Normalwerte zweier Perioden: 1961-1990 (_6190) und 1991-2020 (_9120).
-- Raster: Gauß-Krüger Zone 3 (EPSG:31467), Geometrie in klima_raster_gitter.
-- Quelle: Deutscher Wetterdienst, Climate Data Center (CDC), CC BY 4.0

CREATE TABLE IF NOT EXISTS klima_raster (
    spalte            SMALLINT NOT NULL,   -- 0 = Westrand
    zeile             SMALLINT NOT NULL,   -- 0 = Nordrand (Reihenfolge wie in der .asc-Datei)
    heiss_6190        FLOAT NULL,          -- heiße Tage (Tmax >= 30 °C) je Jahr
    heiss_9120        FLOAT NULL,
    sommer_6190       FLOAT NULL,          -- Sommertage (Tmax >= 25 °C)
    sommer_9120       FLOAT NULL,
    frost_6190        FLOAT NULL,          -- Frosttage (Tmin < 0 °C)
    frost_9120        FLOAT NULL,
    eis_6190          FLOAT NULL,          -- Eistage (Tmax < 0 °C)
    eis_9120          FLOAT NULL,
    starkregen_6190   FLOAT NULL,          -- Tage mit >= 20 mm Niederschlag
    starkregen_9120   FLOAT NULL,
    schnee_6190       FLOAT NULL,          -- Tage mit Schneedecke
    schnee_9120       FLOAT NULL,
    sonne_6190        FLOAT NULL,          -- Sonnenscheindauer in Stunden je Jahr
    sonne_9120        FLOAT NULL,
    PRIMARY KEY (spalte, zeile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS klima_raster_gitter (
    id         TINYINT  NOT NULL PRIMARY KEY DEFAULT 1,
    xll        DOUBLE   NOT NULL,           -- linke untere Ecke (GK3 Rechtswert)
    yll        DOUBLE   NOT NULL,           -- linke untere Ecke (GK3 Hochwert)
    zellgroesse DOUBLE  NOT NULL,           -- 1000 m
    spalten    SMALLINT NOT NULL,
    zeilen     SMALLINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
