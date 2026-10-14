-- BBSR INKAR 2025 (Gebietsstand 31.12.2023), Issue #18. Befüllt von bin/import_inkar.php.
-- Nur die in app/Support/InkarIndikatoren.php ausgewählten Indikatoren, Jahre ab 2015.
-- Quellvermerk: "Laufende Raumbeobachtung des BBSR - INKAR, Ausgabe 07/2025", dl-de/by-2.0

CREATE TABLE IF NOT EXISTS inkar_werte (
    ebene    ENUM('GEM','KRS') NOT NULL,
    ags      VARCHAR(8)        NOT NULL,   -- GEM: 8-stellig, KRS: 5-stellig (wie gebiete_*)
    kuerzel  VARCHAR(40)       NOT NULL,   -- INKAR-Kürzel, z.B. a_Q07_HA_ANT
    jahr     SMALLINT          NOT NULL,
    wert     DOUBLE            NOT NULL,
    PRIMARY KEY (ebene, ags, kuerzel, jahr),
    INDEX idx_kuerzel_jahr (kuerzel, jahr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Je Indikator: neuestes Jahr, Wert für ganz Deutschland (INKAR-Ebene "Bund",
-- bevölkerungsgewichtet — der eigentliche Vergleichswert) und Median über alle
-- Gemeinden bzw. Kreise (die "mittlere Gemeinde" ist ein Dorf, nur zur Info).
CREATE TABLE IF NOT EXISTS inkar_indikatoren (
    kuerzel    VARCHAR(40)       NOT NULL PRIMARY KEY,
    ebene      ENUM('GEM','KRS') NOT NULL,
    inkar_id   INT               NOT NULL,
    jahr_neu   SMALLINT          NOT NULL,
    wert_bund  DOUBLE            NULL,
    median     DOUBLE            NOT NULL,
    anzahl     INT               NOT NULL,  -- Gebiete mit Wert im neuesten Jahr
    importiert TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
