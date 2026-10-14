-- Unfallatlas der Statistischen Ämter des Bundes und der Länder (Issue #20):
-- Straßenverkehrsunfälle mit Personenschaden, punktgenau. Befüllt von bin/import_unfaelle.php.
-- Lizenz dl-de/by-2.0, Quellvermerk "Statistische Ämter des Bundes und der Länder, Unfallatlas".

CREATE TABLE IF NOT EXISTS unfaelle (
    uid        CHAR(20)  NOT NULL PRIMARY KEY,   -- UIDENTSTLAE
    jahr       SMALLINT  NOT NULL,
    monat      TINYINT   NOT NULL,
    stunde     TINYINT   NOT NULL,
    kategorie  TINYINT   NOT NULL,               -- 1 = Getötete, 2 = Schwerverletzte, 3 = Leichtverletzte
    rad        TINYINT(1) NOT NULL,
    fuss       TINYINT(1) NOT NULL,
    pkw        TINYINT(1) NOT NULL,
    krad       TINYINT(1) NOT NULL,
    gkfz       TINYINT(1) NOT NULL,              -- Güterkraftfahrzeug
    lat        DOUBLE    NOT NULL,
    lon        DOUBLE    NOT NULL,
    pt         POINT     NOT NULL,               -- x = lon, y = lat (SRID 0)
    SPATIAL INDEX sp_pt (pt),
    INDEX idx_jahr (jahr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
