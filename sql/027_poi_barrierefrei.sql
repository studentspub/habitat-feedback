-- Issue #1: Merkmal „barrierefrei“ je POI (bisher nur für Toiletten gefüllt, aus wheelchair / toilets:wheelchair),
-- damit die Karte einen Unterschalter „nur barrierefreie Toiletten“ anbieten kann, ohne im Namenstext zu suchen.
-- IF NOT EXISTS: MariaDB.
ALTER TABLE poi_locations ADD COLUMN IF NOT EXISTS barrierefrei TINYINT(1) NOT NULL DEFAULT 0 AFTER name;
