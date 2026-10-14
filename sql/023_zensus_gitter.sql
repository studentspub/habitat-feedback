-- Zensus 2022 Gitterdaten (Destatis), lokal statt live über den ArcGIS-Dienst von Esri (Issue #23).
-- Befüllt von bin/import_zensus.php. Lizenz dl-de/by-2.0, Quelle "Statistisches Bundesamt (Destatis), Zensus 2022".
--
-- x/y = Zellmittelpunkt in ETRS89-LAEA (EPSG:3035, Meter), wie in den Download-Dateien.
-- kx/ky = 10-km-Kachel (x DIV 10000, y DIV 10000): Ausschnittsabfragen lesen über den
-- Index nur die betroffenen Kacheln statt eines ganzen Längsstreifens Deutschlands.

CREATE TABLE IF NOT EXISTS zensus_gitter (
    aufloesung  MEDIUMINT NOT NULL,         -- Zellgröße in m: 100, 1000, 10000
    x           INT       NOT NULL,
    y           INT       NOT NULL,
    kx          SMALLINT  NOT NULL,
    ky          SMALLINT  NOT NULL,
    einwohner   INT       NULL,
    alter_d     FLOAT     NULL,             -- Durchschnittsalter
    miete_qm    FLOAT     NULL,             -- durchschnittliche Nettokaltmiete €/m²
    eigentum    FLOAT     NULL,             -- Eigentümerquote %
    leerstand   FLOAT     NULL,             -- Leerstandsquote %
    PRIMARY KEY (aufloesung, x, y),
    INDEX idx_kachel (aufloesung, kx, ky)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
