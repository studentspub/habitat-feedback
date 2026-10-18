-- Index für die Ausschnittsabfrage der Bewertungs-Pins (/api/map, SegmentRepository::visiblePinsInBbox), Issue #29.
-- Heute hat segments nur eine Handvoll Zeilen; ohne Index liest jeder Kartenschwenk aber die ganze Tabelle,
-- und sobald Bewertungen zusammenkommen, wird daraus die teuerste Abfrage der Karte. Billig jetzt, nötig später.
-- IF NOT EXISTS: MariaDB.
CREATE INDEX IF NOT EXISTS idx_centroid ON segments (centroid_lat, centroid_lon);
