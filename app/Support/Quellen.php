<?php

namespace App\Support;

use PDO;

/**
 * Alle Quellen der Seite mit Herausgeber, Lizenz, Datenstand und Verwendung
 * (Issue #33). Eine Liste für die Seite /quellen und für die Über-Seite,
 * damit nichts auseinanderläuft. Regel des Users (18.10.2026): jede
 * Information, die auf der Seite abrufbar ist (außer den Bewertungen selbst),
 * braucht hier einen Eintrag - bis hinunter zur einzelnen Kennzahl.
 *
 * Datenstände, die in der Datenbank stehen (Berichtsmonat, Stichtag,
 * Jahresspanne), werden gelesen und nicht hier gepflegt; fehlt die Tabelle,
 * steht „unbekannt“ statt eines veralteten Werts.
 */
final class Quellen
{
    /** Lizenzen: Kürzel => [Anzeigename, Link]. */
    public const LIZENZEN = [
        'odbl'  => ['Open Data Commons Open Database License (ODbL) 1.0', 'https://opendatacommons.org/licenses/odbl/1-0/'],
        'dlde'  => ['Datenlizenz Deutschland – Namensnennung – Version 2.0 (dl-de/by-2.0)', 'https://www.govdata.de/dl-de/by-2-0'],
        'ccby4' => ['Creative Commons Namensnennung 4.0 International (CC BY 4.0)', 'https://creativecommons.org/licenses/by/4.0/deed.de'],
        'bsd2'  => ['BSD-2-Clause', '/vendor/leaflet-1.9.4/LICENSE'],
        'agpl'  => ['GNU Affero General Public License v3.0 (AGPL-3.0)', 'https://www.gnu.org/licenses/agpl-3.0.de.html'],
        'ba'    => ['Nutzungsbedingungen der Statistik der Bundesagentur für Arbeit (Quellenangabe)', 'https://statistik.arbeitsagentur.de'],
        'bka'   => ['Veröffentlichung des Bundeskriminalamts, Nutzung mit Quellenangabe', 'https://www.bka.de/DE/AktuelleInformationen/StatistikenLagebilder/PolizeilicheKriminalstatistik/pks_node.html'],
        'uba'   => ['Umweltbundesamt, Luftdaten-Schnittstelle (Quellenangabe)', 'https://luftdaten.umweltbundesamt.de'],
        'bnetza_wms' => ['Bundesnetzagentur, Breitbandatlas-WMS (frei nutzbar mit Quellenangabe)', 'https://gigabitgrundbuch.bund.de'],
        'eea'   => ['Europäische Umweltagentur, Standard-Weiterverwendungsrichtlinie (Quellenangabe)', 'https://www.eea.europa.eu/en/legal-notice'],
        'bfg'   => ['Bundesanstalt für Gewässerkunde / Länder, Geoportal (Quellenangabe)', 'https://geoportal.bafg.de/karten/HWRM/'],
        'osmf_tiles' => ['OSMF Tile Usage Policy; Daten ODbL', 'https://operations.osmfoundation.org/policies/tiles/'],
        'osmf_nominatim' => ['OSMF Nominatim Usage Policy; Daten ODbL', 'https://operations.osmfoundation.org/policies/nominatim/'],
        'google' => ['Nutzungsbedingungen von Google Maps (nur Verweis, keine Datenübernahme)', 'https://policies.google.com/terms'],
        'eigen' => ['Eigene Texte und Daten des Betreibers', '/projekt'],
    ];

    private const OSM_VERMERK = '© OpenStreetMap-Mitwirkende';
    private const BKG_VERMERK = '© GeoBasis-DE / BKG (2023)';
    private const GENESIS = 'Regionaldatenbank Deutschland (Statistische Ämter des Bundes und der Länder)';
    private const GENESIS_LINK = 'https://www.regionalstatistik.de';
    private const INKAR_VERMERK = 'Laufende Raumbeobachtung des BBSR – INKAR, Ausgabe 07/2025';

    /** Anzeigenamen der POI-Kategorien wie auf der Karte (map.js POI_CATEGORIES). */
    private const POI_LABELS = [
        'playground' => 'Spielplätze', 'food_drink' => 'Kneipen & Cafés', 'leisure' => 'Freizeit', 'university' => 'Unis',
        'school' => 'Kitas & Schulen', 'doctor' => 'Ärzte & Apotheken', 'supermarket' => 'Supermärkte', 'transit' => 'ÖPNV-Haltestellen',
        'toilets' => 'Toiletten', 'post' => 'Post & Pakete', 'ebike' => 'E-Bike-Laden',
    ];

    /**
     * Datenstände aus der Datenbank. Jeder Wert einzeln abgesichert: eine
     * fehlende Tabelle (frische Installation) darf die Seite nicht brechen.
     *
     * @return array<string, string>
     */
    public static function staende(?PDO $db): array
    {
        $lies = static function (string $sql, string $fallback = 'unbekannt') use ($db): string {
            if ($db === null) {
                return $fallback;
            }
            try {
                $v = $db->query($sql)->fetchColumn();
                return $v !== false && $v !== null && $v !== '' ? (string) $v : $fallback;
            } catch (\PDOException $e) {
                return $fallback;
            }
        };
        $jahr = static fn(string $tabelle): string => $lies("SELECT MAX(reporting_year) FROM $tabelle");
        return [
            'arbeitslos' => $lies('SELECT reporting_month FROM unemployment_rates ORDER BY reporting_month DESC LIMIT 1'),
            'pks' => $jahr('crime_rates'),
            'grundsicherung' => $jahr('grundsicherung_alter'),
            'elterngeld' => $jahr('elterngeld_bezieher'),
            'wohnungsbestand' => $jahr('wohnungsbestand'),
            'einkommen' => $jahr('einkommen_haushalte'),
            'wanderung' => $jahr('wanderungssaldo'),
            'baugenehmigungen' => $jahr('baugenehmigungen'),
            'baufertigstellungen' => $jahr('baufertigstellungen'),
            'gewerbe' => $jahr('gewerbeanzeigen'),
            'insolvenzen' => $jahr('insolvenzen'),
            'inkar' => $lies('SELECT CONCAT(MIN(jahr), "–", MAX(jahr)) FROM inkar_werte'),
            'unfaelle' => $lies('SELECT CONCAT(MIN(jahr), "–", MAX(jahr)) FROM unfaelle'),
            'oepnv' => $lies('SELECT CONCAT("Werktag ", DATE_FORMAT(stichtag, "%d.%m.%Y"), ", Feed ", feed_datei) FROM oepnv_stand WHERE id = 1'),
            'strassen' => $lies('SELECT DATE_FORMAT(osm_stand, "%d.%m.%Y") FROM strassen_stand WHERE id = 1'),
            'poi' => $lies('SELECT DATE_FORMAT(MAX(imported_at), "%d.%m.%Y") FROM poi_locations WHERE category <> "charging"'),
            'ladesaeulen' => $lies('SELECT DATE_FORMAT(MAX(imported_at), "%d.%m.%Y") FROM poi_locations WHERE category = "charging"'),
            'luft_stationen' => $lies('SELECT COUNT(*) FROM air_stations'),
            'klima_stationen' => $lies('SELECT COUNT(*) FROM climate_normals'),
        ];
    }

    /**
     * Gruppen mit Einträgen. Jeder Eintrag: anzeige (was auf der Seite steht),
     * quelle (Datensatz), herausgeber, lizenz (Kürzel aus LIZENZEN), vermerk
     * (Pflichttext, falls vorgeschrieben), stand, link, details (optional).
     *
     * @return list<array{titel: string, hinweis?: string, eintraege: list<array<string, string>>}>
     */
    public static function gruppen(?PDO $db): array
    {
        $s = self::staende($db);
        $poiZeilen = [];
        foreach (PoiCategories::CATEGORIES as $key => $filter) {
            $poiZeilen[] = [
                'anzeige' => 'Freizeit & Infrastruktur: ' . (self::POI_LABELS[$key] ?? $key),
                'quelle' => 'OpenStreetMap, Tags ' . implode(', ', array_map(static fn(string $f): string => explode('][', $f)[0], $filter)),
                'herausgeber' => 'OpenStreetMap-Mitwirkende (Geofabrik-Extrakt Deutschland bzw. Overpass API)',
                'lizenz' => 'odbl', 'vermerk' => self::OSM_VERMERK, 'stand' => 'Import ' . $s['poi'],
                'link' => 'https://www.openstreetmap.org/copyright',
            ];
        }
        $poiZeilen[] = [
            'anzeige' => 'Freizeit & Infrastruktur: Ladesäulen (Autos)',
            'quelle' => 'Ladesäulenregister, „Liste der Ladesäulen (CSV)“, nur Ladeeinrichtungen in Betrieb',
            'herausgeber' => 'Bundesnetzagentur', 'lizenz' => 'ccby4', 'vermerk' => 'Bundesnetzagentur.de',
            'stand' => 'Import ' . $s['ladesaeulen'], 'link' => 'https://www.bundesnetzagentur.de/ladesaeulenkarte',
        ];

        $inkarZeilen = [];
        foreach (InkarIndikatoren::LISTE as $kuerzel => $ind) {
            $inkarZeilen[] = [
                'anzeige' => 'Lebensqualität, ' . InkarIndikatoren::GRUPPEN[$ind['gruppe']] . ': ' . $ind['label'] . ($ind['einheit'] !== '' ? ' (' . $ind['einheit'] . ')' : ''),
                'quelle' => 'INKAR-Indikator ' . $kuerzel . ' (ID ' . $ind['id'] . '), Ebene ' . ($ind['ebene'] === 'GEM' ? 'Gemeinde' : 'Kreis') . ', Vergleichswert Deutschland aus derselben Quelle',
                'herausgeber' => 'Bundesinstitut für Bau-, Stadt- und Raumforschung (BBSR)', 'lizenz' => 'dlde', 'vermerk' => self::INKAR_VERMERK,
                'stand' => 'Ausgabe 07/2025, Jahrgänge ' . $s['inkar'] . ' (je Kennzahl der neueste)', 'link' => 'https://www.inkar.de',
            ];
        }

        $genesis = static fn(string $anzeige, string $tabelle, string $beschreibung, string $stand): array => [
            'anzeige' => 'Wirtschaft (Kreis): ' . $anzeige,
            'quelle' => $beschreibung . ', Tabelle ' . $tabelle,
            'herausgeber' => self::GENESIS, 'lizenz' => 'dlde', 'vermerk' => 'Statistische Ämter des Bundes und der Länder, Regionaldatenbank Deutschland',
            'stand' => 'Berichtsjahr ' . $stand, 'link' => self::GENESIS_LINK,
        ];

        return [
            [
                'titel' => 'Karte und Bedienung',
                'eintraege' => [
                    ['anzeige' => 'Kartenhintergrund (Straßen, Gebäude, Gewässer, Beschriftung)', 'quelle' => 'OpenStreetMap-Standardkacheln (tile.openstreetmap.org), live beim Betrachten geladen',
                        'herausgeber' => 'OpenStreetMap Foundation / OpenStreetMap-Mitwirkende', 'lizenz' => 'osmf_tiles', 'vermerk' => self::OSM_VERMERK, 'stand' => 'laufend aktuell', 'link' => 'https://www.openstreetmap.org/copyright'],
                    ['anzeige' => 'Deutschland-Umriss (Nebel über dem Ausland, Bewegungsgrenze der Karte)', 'quelle' => 'VG250 Verwaltungsgebiete 1:250 000, Ebene Staat, auf 40 m vereinfacht (public/data/de_grenze.geojson)',
                        'herausgeber' => 'Bundesamt für Kartographie und Geodäsie (BKG)', 'lizenz' => 'dlde', 'vermerk' => self::BKG_VERMERK, 'stand' => 'Gebietsstand 31.12.2023', 'link' => 'https://gdz.bkg.bund.de'],
                    ['anzeige' => 'Zuordnung der Kartenmitte zu Gemeinde und Kreis (Name, Gemeindeschlüssel)', 'quelle' => 'VG250 Verwaltungsgebiete 1:250 000, Ebenen Gemeinde und Kreis',
                        'herausgeber' => 'Bundesamt für Kartographie und Geodäsie (BKG)', 'lizenz' => 'dlde', 'vermerk' => self::BKG_VERMERK, 'stand' => 'Gebietsstand 31.12.2023', 'link' => 'https://gdz.bkg.bund.de'],
                    ['anzeige' => 'Straßenansicht-Männchen: Link „bei Google öffnen“', 'quelle' => 'Verweis auf Google Maps Street View über eine Maps-URL; es werden keine Daten von Google geladen oder gespeichert, der Aufruf passiert erst mit deinem Klick',
                        'herausgeber' => 'Google', 'lizenz' => 'google', 'vermerk' => '–', 'stand' => '–', 'link' => 'https://developers.google.com/maps/documentation/urls'],
                    ['anzeige' => 'Kartenbibliothek (Zoom, Ebenen, Popups, Maßstab)', 'quelle' => 'Leaflet 1.9.4, selbst gehostet unter /vendor/',
                        'herausgeber' => 'Volodymyr Agafonkin und Mitwirkende', 'lizenz' => 'bsd2', 'vermerk' => 'Leaflet © 2010–2023 Volodymyr Agafonkin, © 2010–2011 CloudMade', 'stand' => 'Version 1.9.4', 'link' => 'https://leafletjs.com'],
                ],
            ],
            [
                'titel' => 'Suche, Adressen und Bewertungsformular',
                'eintraege' => [
                    ['anzeige' => '„Adresse finden“ und Vorschläge im Bewertungsformular: Straßen mit PLZ und Ort', 'quelle' => 'Eigenes Straßenverzeichnis aus dem OpenStreetMap-Extrakt Deutschland (Straßen, Adresspunkte, PLZ-Gebiete), eine Zeile je Straße + PLZ + Ort, ohne Hausnummern',
                        'herausgeber' => 'OpenStreetMap-Mitwirkende, Extrakt von Geofabrik', 'lizenz' => 'odbl', 'vermerk' => self::OSM_VERMERK, 'stand' => 'OSM-Datenstand ' . $s['strassen'], 'link' => 'https://download.geofabrik.de'],
                    ['anzeige' => '„Adresse finden“: Orte (Gemeinden mit Umriss)', 'quelle' => 'VG250 Verwaltungsgebiete 1:250 000, Ebene Gemeinde',
                        'herausgeber' => 'Bundesamt für Kartographie und Geodäsie (BKG)', 'lizenz' => 'dlde', 'vermerk' => self::BKG_VERMERK, 'stand' => 'Gebietsstand 31.12.2023', 'link' => 'https://gdz.bkg.bund.de'],
                    ['anzeige' => 'Zuordnung einer abgeschickten Bewertung zum Straßenabschnitt (Geokodierung)', 'quelle' => 'Nominatim (einmal je Bewertung, nicht beim Tippen), ausgeführt vom Hintergrund-Worker',
                        'herausgeber' => 'OpenStreetMap Foundation', 'lizenz' => 'osmf_nominatim', 'vermerk' => self::OSM_VERMERK, 'stand' => 'laufend aktuell', 'link' => 'https://nominatim.org'],
                    ['anzeige' => 'Zahl der Adressen je Straßenabschnitt (Sichtbarkeitsschwelle)', 'quelle' => 'Overpass API, Adresspunkte im Abschnitt, ausgeführt vom Hintergrund-Worker',
                        'herausgeber' => 'OpenStreetMap-Mitwirkende', 'lizenz' => 'odbl', 'vermerk' => self::OSM_VERMERK, 'stand' => 'zum Zeitpunkt der Bewertung', 'link' => 'https://wiki.openstreetmap.org/wiki/Overpass_API'],
                    ['anzeige' => '„Bereits bewertete Straßen“ und die Bewertungs-Punkte mit Durchschnitten', 'quelle' => 'Eigene Erhebung: anonyme Bewertungen der Nutzer, nur als Durchschnitt je Abschnitt ab 6 Adressen und 3 Bewertungen sichtbar',
                        'herausgeber' => 'Habitat Feedback', 'lizenz' => 'eigen', 'vermerk' => '–', 'stand' => 'laufend', 'link' => '/ueber'],
                ],
            ],
            [
                'titel' => 'Amtliche Statistik (Zensus 2022)',
                'hinweis' => 'Fünf Kennzahlen als Raster 100 m / 1 km / 10 km, je nach Zoom; Zellen ohne Wert bleiben leer.',
                'eintraege' => array_map(static fn(array $z): array => [
                    'anzeige' => 'Zensus: ' . $z[0], 'quelle' => 'Zensus 2022, Gitterdaten „' . $z[1] . '“ (100 m, 1 km, 10 km)',
                    'herausgeber' => 'Statistisches Bundesamt (Destatis)', 'lizenz' => 'dlde', 'vermerk' => 'Statistisches Bundesamt (Destatis), Zensus 2022',
                    'stand' => 'Zensusstichtag 15.05.2022', 'link' => 'https://www.zensus2022.de/DE/Ergebnisse-des-Zensus/gitterzellen.html',
                ], [
                    ['Einwohnerdichte (Einwohner je Zelle)', 'Bevölkerungszahl in Gitterzellen'],
                    ['Ø Alter', 'Durchschnittsalter in Gitterzellen'],
                    ['Ø Miete/m² (Nettokaltmiete)', 'Durchschnittliche Nettokaltmiete in Gitterzellen'],
                    ['Eigentümerquote', 'Eigentümerquote in Gitterzellen'],
                    ['Leerstandsquote', 'Leerstandsquote in Gitterzellen'],
                ]),
            ],
            [
                'titel' => 'Freizeit & Infrastruktur',
                'hinweis' => 'Punkte ab Stadtteil-Zoom; einmal eingelesen, nicht bei jedem Kartenschwenk abgefragt. Tooltip-Texte (Art, Marke, „barrierefrei“, „kostenlos“) stammen aus den OpenStreetMap-Tags.',
                'eintraege' => $poiZeilen,
            ],
            [
                'titel' => 'Klima & Luft',
                'eintraege' => [
                    ['anzeige' => 'Ø Jahrestemperatur, wärmster und kältester Monat', 'quelle' => 'Vieljährige Stationsmittel 1991–2020, Lufttemperatur (CDC, multi_annual/mean_91-20), nächste Station zur Kartenmitte',
                        'herausgeber' => 'Deutscher Wetterdienst (DWD)', 'lizenz' => 'ccby4', 'vermerk' => 'Quelle: Deutscher Wetterdienst', 'stand' => 'Normalperiode 1991–2020, ' . $s['klima_stationen'] . ' Stationen', 'link' => 'https://opendata.dwd.de/climate_environment/CDC/'],
                    ['anzeige' => 'Ø Jahresniederschlag', 'quelle' => 'Vieljährige Stationsmittel 1991–2020, Niederschlag (CDC, multi_annual/mean_91-20), nächste Station zur Kartenmitte',
                        'herausgeber' => 'Deutscher Wetterdienst (DWD)', 'lizenz' => 'ccby4', 'vermerk' => 'Quelle: Deutscher Wetterdienst', 'stand' => 'Normalperiode 1991–2020', 'link' => 'https://opendata.dwd.de/climate_environment/CDC/'],
                    ['anzeige' => 'Heiße Tage, Sommertage, Frosttage, Tage mit Schneedecke, Starkregentage, Sonnenstunden („heute“ und „früher“)', 'quelle' => 'Vieljährige Rasterdaten 1 km (CDC, grids_germany/multi_annual), Perioden 1991–2020 und 1961–1990, Zelle der Kartenmitte',
                        'herausgeber' => 'Deutscher Wetterdienst (DWD)', 'lizenz' => 'ccby4', 'vermerk' => 'Quelle: Deutscher Wetterdienst', 'stand' => 'Normalperioden 1961–1990 und 1991–2020', 'link' => 'https://opendata.dwd.de/climate_environment/CDC/grids_germany/multi_annual/'],
                    ['anzeige' => 'Luftqualität: Index (sehr gut bis sehr schlecht), PM10, NO₂, O₃, Stationsname', 'quelle' => 'Luftdaten-API v4 (Stationsliste einmal importiert, Messwerte live, bis 3 Stunden zwischengespeichert), nächste Station mit Messwerten',
                        'herausgeber' => 'Umweltbundesamt (UBA) mit den Messnetzen der Länder', 'lizenz' => 'uba', 'vermerk' => 'Umweltbundesamt, luftdaten.umweltbundesamt.de', 'stand' => 'live (Stundenwerte, einige Tage verzögert), ' . $s['luft_stationen'] . ' Stationen', 'link' => 'https://luftdaten.umweltbundesamt.de'],
                ],
            ],
            [
                'titel' => 'Zuschaltbare Ebenen von Drittservern (werden erst beim Einschalten geladen)',
                'eintraege' => [
                    ['anzeige' => 'Breitband: Festnetz-Verfügbarkeit ≥ 50 Mbit/s, alle Anbieter, 100-m-Raster', 'quelle' => 'Breitbandatlas, WMS-Ebene „Breitband_Festnetz 100m Gitter Alle Unternehmen ≥ 50 Mbit/s“, fertige Kacheln',
                        'herausgeber' => 'Bundesnetzagentur (Gigabit-Grundbuch)', 'lizenz' => 'bnetza_wms', 'vermerk' => '© BNetzA', 'stand' => 'Datenstand 12.2025 (laut Dienst)', 'link' => 'https://gigabitgrundbuch.bund.de'],
                    ['anzeige' => 'Lärm: Straßen- und Schienenlärm Lden (Farbstufen 55 bis ≥ 75 dB)', 'quelle' => 'Umgebungslärmkartierung nach EU-Richtlinie 2002/49/EG (END), WMS „NoiseContours_road_lden“ und „NoiseContours_rail_lden“',
                        'herausgeber' => 'Europäische Umweltagentur (EEA), Daten der Mitgliedstaaten', 'lizenz' => 'eea', 'vermerk' => 'Lärm: EEA (END-Lärmkartierung)', 'stand' => 'letzte Kartierungsrunde laut Dienst', 'link' => 'https://www.eea.europa.eu/en/datahub'],
                    ['anzeige' => 'Hochwasser: Überflutungsflächen HQ100 an Flüssen und Küste', 'quelle' => 'Hochwassergefahrenkarten nach HWRM-RL, WMS HWRMRL_DE_LM und HWRMRL_DE_SM, Ebene „0“',
                        'herausgeber' => 'Bundesanstalt für Gewässerkunde (BfG) mit den Ländern', 'lizenz' => 'bfg', 'vermerk' => 'Hochwasser: BfG / Länder (HWRM-RL)', 'stand' => 'Stand 2019 (laut Dienst)', 'link' => 'https://geoportal.bafg.de/karten/HWRM/'],
                ],
            ],
            [
                'titel' => 'Wirtschaft (Kreis der Kartenmitte)',
                'eintraege' => [
                    ['anzeige' => 'Wirtschaft (Kreis): Arbeitslosenquote', 'quelle' => 'Eckwerte des Arbeitsmarktes nach Kreisen (Tabelle EckwerteTabelleALOKr), Quote bezogen auf alle zivilen Erwerbspersonen',
                        'herausgeber' => 'Statistik der Bundesagentur für Arbeit', 'lizenz' => 'ba', 'vermerk' => 'Statistik der Bundesagentur für Arbeit', 'stand' => 'Berichtsmonat ' . $s['arbeitslos'], 'link' => 'https://statistik.arbeitsagentur.de'],
                    ['anzeige' => 'Wirtschaft (Kreis): Straftaten je 100.000 Einwohner (Häufigkeitszahl)', 'quelle' => 'Polizeiliche Kriminalstatistik (PKS), Tabelle 01 „Kreise – Fälle – HZ“, alle Straftaten',
                        'herausgeber' => 'Bundeskriminalamt (BKA)', 'lizenz' => 'bka', 'vermerk' => 'Polizeiliche Kriminalstatistik (BKA)', 'stand' => 'Berichtsjahr ' . $s['pks'], 'link' => 'https://www.bka.de/DE/AktuelleInformationen/StatistikenLagebilder/PolizeilicheKriminalstatistik/pks_node.html'],
                    $genesis('Empfänger Grundsicherung im Alter', '22151-01-02-4', 'Statistik der Empfänger von Grundsicherung im Alter und bei Erwerbsminderung (Statistik 22151)', $s['grundsicherung']),
                    $genesis('Elterngeld-Bezieher (4 Quartale)', '22922-01-01-4', 'Statistik zum Elterngeld, Leistungsbezüge je Quartal, Summe der letzten vier (Statistik 22922)', $s['elterngeld']),
                    $genesis('Wohnungsbestand', '31231-02-01-4', 'Fortschreibung des Wohngebäude- und Wohnungsbestandes, Stichtag 31.12. (Statistik 31231)', $s['wohnungsbestand']),
                    $genesis('Verfügbares Einkommen je Einwohner', '82000-07-01-4', 'Volkswirtschaftliche Gesamtrechnungen der Länder, verfügbares Einkommen der privaten Haushalte (Statistik 82000)', $s['einkommen']),
                    $genesis('Wanderungssaldo', '12711-91-01-4', 'Wanderungsstatistik, Zuzüge minus Fortzüge über Kreisgrenzen (Statistik 12711)', $s['wanderung']),
                    $genesis('Baugenehmigungen (Wohnungen)', '31111-01-02-4', 'Statistik der Baugenehmigungen, genehmigte Wohnungen (Statistik 31111)', $s['baugenehmigungen']),
                    $genesis('Baufertigstellungen (Wohnungen)', '31121-01-02-4', 'Statistik der Baufertigstellungen, fertiggestellte Wohnungen (Statistik 31121)', $s['baufertigstellungen']),
                    $genesis('Gewerbe-Saldo (An- minus Abmeldungen)', '52311-01-04-4', 'Gewerbeanzeigenstatistik, Anmeldungen und Abmeldungen (Statistik 52311)', $s['gewerbe']),
                    $genesis('Insolvenzen (gesamt)', '52411-01-01-4', 'Insolvenzstatistik, beantragte Verfahren insgesamt (Statistik 52411)', $s['insolvenzen']),
                ],
            ],
            [
                'titel' => 'Lebensqualität (BBSR INKAR), ÖPNV und Unfälle',
                'hinweis' => 'Alle INKAR-Kennzahlen einzeln; „DE“ ist jeweils der Wert für Deutschland aus derselben Quelle. Grün/rot markiert nur den Vergleich zum Bundeswert, keine Wertung.',
                'eintraege' => array_merge($inkarZeilen, [
                    ['anzeige' => 'ÖPNV im Umkreis: Haltestellen, Abfahrten je Werktag, Verkehrsmittel; Kartenebene „ÖPNV-Takt“', 'quelle' => 'GTFS-Feed „Deutschland komplett“ (Fahrplandaten aller Verbünde), Abfahrten je Station an einem normalen Werktag ausgezählt',
                        'herausgeber' => 'DELFI e.V., bereitgestellt über gtfs.de', 'lizenz' => 'ccby4', 'vermerk' => 'Fahrplandaten: DELFI e.V. über GTFS.de (CC 4.0)', 'stand' => $s['oepnv'], 'link' => 'https://gtfs.de/de/feeds/de_full/'],
                    ['anzeige' => 'Unfälle im Umkreis (gesamt, Getötete/Schwerverletzte, mit Rad, mit Fußgängern), Unfallzeile im Bewertungs-Popup, Kartenebene „Unfallorte“', 'quelle' => 'Unfallatlas: Straßenverkehrsunfälle mit Personenschaden, punktgenau, Jahresdateien',
                        'herausgeber' => 'Statistische Ämter des Bundes und der Länder', 'lizenz' => 'dlde', 'vermerk' => 'Statistische Ämter des Bundes und der Länder, Unfallatlas', 'stand' => 'Unfalljahre ' . $s['unfaelle'], 'link' => 'https://unfallatlas.statistikportal.de'],
                ]),
            ],
            [
                'titel' => 'Texte ohne Datenquelle',
                'eintraege' => [
                    ['anzeige' => 'Seite „Vision“: Beispielkarte mit Bewertungen', 'quelle' => 'Frei erfundene Beispieldaten (fester Pseudozufall), keine echten Bewertungen; Städtekoordinaten grob, Straßennamen generisch',
                        'herausgeber' => 'Habitat Feedback', 'lizenz' => 'eigen', 'vermerk' => '–', 'stand' => '–', 'link' => '/vision'],
                    ['anzeige' => 'Praxisbeispiele (fünf Personen)', 'quelle' => 'Fiktive Personen und Situationen als Lesehilfe für die Kennzahlen',
                        'herausgeber' => 'Habitat Feedback', 'lizenz' => 'eigen', 'vermerk' => '–', 'stand' => '–', 'link' => '/'],
                    ['anzeige' => 'Erklärtexte: Tutorial, Info-Knöpfe „i“, Hinweise in den Bedienfeldern, Über-Seite', 'quelle' => 'Eigene Texte des Betreibers; die Einordnungen („Signal für …“) sind Lesehilfen, keine amtlichen Bewertungen',
                        'herausgeber' => 'Habitat Feedback', 'lizenz' => 'eigen', 'vermerk' => '–', 'stand' => '–', 'link' => '/projekt'],
                ],
            ],
            [
                'titel' => 'Software',
                'eintraege' => [
                    ['anzeige' => 'Die Seite selbst (PHP, JavaScript, SQL)', 'quelle' => 'Eigener Quellcode, Veröffentlichung auf GitHub mit dem Livegang',
                        'herausgeber' => 'Habitat Feedback', 'lizenz' => 'agpl', 'vermerk' => '–', 'stand' => 'laufend', 'link' => '/projekt'],
                    ['anzeige' => 'Kartenbibliothek', 'quelle' => 'Leaflet 1.9.4 (JavaScript, CSS, Marker-Bilder)',
                        'herausgeber' => 'Volodymyr Agafonkin und Mitwirkende', 'lizenz' => 'bsd2', 'vermerk' => 'Leaflet © 2010–2023 Volodymyr Agafonkin, © 2010–2011 CloudMade', 'stand' => 'Version 1.9.4', 'link' => 'https://leafletjs.com'],
                ],
            ],
        ];
    }
}
