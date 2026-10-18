# Datenquellen - Bestand und Kandidaten

Stand 14.10.2026. Bewertung neuer Quellen für Habitat Feedback. Die Seite bleibt **lokal** (siehe
`ZUSAMMENARBEIT_Claude.md`), trotzdem nur Quellen mit freier Lizenz, damit ein späterer Livegang nichts umbauen muss.
Umsetzung läuft über Issues im Projekt 3, Label `Datenquelle`.

Anlass: Eine andere Seite mit Kreis-Score nennt als Datenbasis BBSR (dl-de/by-2.0) und DWD (CC BY 4.0). Deren
Zahlen übernehmen wir **nicht** (dann müssten wir sie als Quelle nennen), sondern holen die Rohdaten direkt beim
BBSR und beim DWD. Beide Lizenzen erlauben auch kommerzielle Nutzung, Pflicht ist nur die Namensnennung.

## 1. Bestand (schon importiert oder live eingebunden)

| Thema | Quelle | Ebene | Wie | Lizenz |
|---|---|---|---|---|
| Arbeitslosenquote | Bundesagentur für Arbeit, Eckwerte ALO | Kreis | `bin/import_unemployment.php` | BA-Nutzungsbedingungen |
| Kriminalität (Häufigkeitszahl) | BKA, PKS 2025 | Kreis | `bin/import_crime_rates.php` | BKA |
| Grundsicherung, Elterngeld, Wohnungsbestand, Einkommen, Wanderungssaldo, Baugenehmigungen, Baufertigstellungen, Gewerbeanzeigen, Insolvenzen | Regionaldatenbank (GENESIS), Tabellen 22151, 22922, 31231, 82000, 12711, 31111, 31121, 52311, 52411 | Kreis | `bin/import_*.php` | dl-de/by-2.0 |
| Klima-Normalwerte 1991-2020 | DWD, Stationen (`mean_91-20`) | nächste Station | `bin/import_climate_normals.php` | CC BY 4.0 |
| Luftqualität | Umweltbundesamt, Luftdaten-API | nächste Station | `bin/import_air_stations.php` + live | UBA |
| POIs (Spielplätze, Kitas, Ärzte, Supermärkte ...; seit #1 auch Toiletten, Post & Pakete, E-Bike-Laden) | OpenStreetMap über Overpass oder aus dem Geofabrik-Extrakt (`--extrakt`) | Punkt | `bin/import_poi.php` | ODbL |
| Bushaltestellen | OpenStreetMap, Geofabrik-Extrakt | Punkt | `bin/import_bus_stops.php` | ODbL |
| Straßenverzeichnis (Straße + PLZ + Ort, ohne Hausnummern) für die Adress-Vorschläge | OpenStreetMap, Geofabrik-Extrakt (Straßen, Adresspunkte, PLZ-Gebiete) | Straße | `bin/import_strassen.php` (#15), `/api/address-autocomplete` — vorher live über Nominatim | ODbL |
| Zensus 2022 (Dichte, Alter, Miete, Eigentum, Leerstand) | Destatis, Gitterdaten-Downloads | 100 m / 1 km / 10 km | `bin/import_zensus.php` (#23), `/api/zensus` — vorher live über Esri/USA | dl-de/by-2.0 |
| Breitband | Bundesnetzagentur, Breitbandatlas | WMS | **live** im Browser | BNetzA |
| Gemeinde-/Kreisgrenzen (Zuordnung Punkt → Gemeinde/Kreis, Deutschland-Maske) | BKG VG250, Stand 31.12.2023 | Polygone | `bin/import_vg250.php`, `bin/build_de_grenze.php` (#17, #12) | dl-de/by-2.0 |
| 34 Kennzahlen (Fußwege zu Hausarzt/Apotheke/Schule/Supermarkt/Haltestelle, Fahrzeiten, Pendeln, Grün, Unfälle; Ärzte, Kitas, Pflege, Mieten, Bauland, Einbrüche, Pkw, Ladepunkte) | BBSR INKAR 2025 | Gemeinde + Kreis | `bin/import_inkar.php` (#18), Panel „Lebensqualität (BBSR)“ | dl-de/by-2.0 |

Kreis-Zuordnung seit #17 lokal über VG250 (`GebietLookup`), Nominatim-Rückwärtssuche nur noch als Rückfall.

## 2. Kandidaten, nach Nutzen sortiert

| # | Quelle | Was wir bekommen | Ebene | Lizenz | Aufwand | Empfehlung |
|---|---|---|---|---|---|---|
| A ✅ | **BKG VG250** (Verwaltungsgebiete) — umgesetzt #17 | Gemeinde- und Kreisgrenzen mit amtlichem Schlüssel (AGS) | Polygone | dl-de/by-2.0 | M | **Grundlage** für B: Punkt -> Gemeinde/Kreis lokal per `ST_Contains`, ersetzt die Nominatim-Rückwärtssuche |
| B ✅ | **BBSR INKAR 2025** — umgesetzt #18 | ~600 Indikatoren, davon ~25 für uns (Liste unten) | Gemeinde + Kreis | dl-de/by-2.0 | M | **ja**, größter Gewinn |
| C ✅ | **DWD 1-km-Raster** (multi-annual) — umgesetzt #19 | Hitzetage, Sommertage, Frosttage, Tage mit ≥ 20 mm Regen, Schneedeckentage, Sonnenscheindauer - je 1961-1990 **und** 1991-2020 | 1 km | CC BY 4.0 | M | **ja**: Wert genau am Ort statt nächster Station, dazu „früher/heute“ (Klimawandel am eigenen Wohnort) |
| D ✅ | **Unfallatlas** (Statistische Ämter) — umgesetzt #20 | Unfälle mit Personenschaden mit Koordinaten, je Jahr bis 2025, mit Rad-/Fußbeteiligung | Punkt | dl-de/by-2.0 (laut GovData/Mobilithek) | M | **ja**: straßengenau, passt zur Abschnitts-Bewertung („Unfälle im Umkreis 250 m“) |
| E ✅ | **Zensus 2022 Gitter** als lokaler Import — umgesetzt #23 | dieselben Werte wie heute, aber aus der eigenen DB | 100 m | dl-de/by-2.0 | L (große Dateien) | später: keine IP-Übertragung an Esri, schneller |
| F ✅ | Umgebungslärm — umgesetzt #21 über die EEA (END-Lärmkartierung Straße + Schiene, WMS `copernicus.discomap.eea.europa.eu/.../noiseStoryMap/NoiseContours_*_lden/ImageServer/WMSServer`, Stand 04/2026) | Lärmbänder Straße/Schiene/Flughafen | WMS | dl-de/by-2.0 | S | ja, als zuschaltbare Ebene wie Breitband |
| G ✅ | Hochwasser-Gefahrenkarten — umgesetzt #21 über die BfG (HQ100 Flüsse + Küste, WMS `geoportal.bafg.de/arcgis/services/HWRMRL/HWRMRL_DE_LM|SM/MapServer/WMSServer`, Stand 2019) | HQ häufig/100/extrem | WMS | je Land | S | ja, als Ebene |
| H ✅ | Ladesäulenregister (BNetzA) — umgesetzt #22, 116.422 Ladeeinrichtungen in Betrieb | Ladepunkte | Punkt | CC BY 4.0 | S | als neue POI-Kategorie |
| I ✅ | GTFS Deutschland (DELFI/gtfs.de) — umgesetzt #24, 234.861 Stationen mit Abfahrten je Werktag | Abfahrten je Haltestelle -> ÖPNV-Takt | Punkt | CC BY 4.0 | L | später: macht aus „Haltestelle in der Nähe“ eine echte Anbindungsqualität |
| J | Radon-Vorsorgegebiete (BfS) | Radon-Potenzial | WMS | dl-de/by-2.0 | S | optional |
| L | OpenStreetMap: öffentliche (auch barrierefreie) Toiletten, Briefkästen, Packstationen, Post-/DHL-Filialen, E-Bike-Ladestationen — Issue #1 | weitere POI-Kategorien | Punkt | ODbL | M | ja (Wunsch User), gleiche Technik wie die bestehenden POIs |
| K | Bodenrichtwerte (BORIS) | Bodenpreise | je Land verschieden | uneinheitlich | - | nein (Lizenz je Land, teils nicht offen) |
| - | Bundestagswahlen (in INKAR) | Stimmenanteile | Kreis | dl-de/by-2.0 | - | **nein** (Entscheidung User 14.10.2026) |

### INKAR - Auswahl für Habitat Feedback

Download: Gesamtdatei `https://www.bbr-server.de/imagemap/inkar/download/inkar_2025.zip` (434 MB, Stand 07/2025,
Gebietsstand 31.12.2023), Indikatorliste `https://www.inkar.de/documents/Uebersicht der Indikatoren.xlsx`.
Quellvermerk laut BBSR: „Laufende Raumbeobachtung des BBSR - INKAR, Ausgabe 07/2025“, auf Websites mit Link auf
www.bbsr.bund.de.

**Gemeinde-Ebene** (genauer als alles, was wir heute pro Kreis zeigen):
- Erreichbarkeit zu Fuß: Entfernung **und** Anteil Einwohner ≤ 1 km zu Hausarzt, Apotheke, Grundschule,
  Supermarkt/Discounter (2023), ÖV-Haltestelle (2022)
- Erreichbarkeit Autobahn, ICE/IC-Bahnhof, Flughafen (2024), Ober-/Mittelzentrum (2021)
- Pendlersaldo, Anteil Pendler mit Arbeitsweg ≥ 50 km
- Erholungsfläche je Einwohner, Waldfläche, Siedlungsdichte
- Durchschnittsalter, Haushalte mit Kindern, Einwohnerdichte
- Straßenverkehrsunfälle / Verunglückte je Einwohner
- Kaufkraft je Einwohner (Quelle Nexiga, über INKAR unter dl-de/by-2.0)

**Kreis-Ebene:**
- Hausärzte, Kinderärzte je Einwohner (KBV), Krankenhausbetten, Pflegeheimplätze
- Betreuungsquote Kleinkinder / Vorschulkinder
- Angebotsmieten (2010-2024), Baulandpreise
- Wohnungseinbruchdiebstahl, Pkw-Dichte, Ladepunkte je 100.000 Einwohner

Überschneidung mit dem Bestand: Baugenehmigungen, Baufertigstellungen, Wanderungen und Einkommen gibt es auch in
INKAR. Die GENESIS-Importe bleiben vorerst, eine Zusammenlegung ist ein eigenes Issue, falls gewünscht.

## 3. Quellen dieser Bewertung

- INKAR-Startseite mit Gesamtdownloads: https://www.inkar.de/
- BBSR Nutzungshinweise (dl-de/by-2.0, Quellvermerk): https://www.bbsr.bund.de/BBSR/DE/service/nutzungshinweise/_node.html
- DWD Open Data, Lizenz CC BY 4.0: https://opendata.dwd.de/climate_environment/CDC/Terms_of_use.txt
- DWD Raster multi-annual: https://opendata.dwd.de/climate_environment/CDC/grids_germany/multi_annual/
- Unfallatlas Open Data (bundesweit, je Jahr): https://www.opengeodata.nrw.de/produkte/transport_verkehr/unfallatlas/
