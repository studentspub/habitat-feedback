<?php

namespace App\Support;

/**
 * Auswahl aus BBSR INKAR 2025 für Habitat Feedback (Issue #18, Bewertung in
 * DATENQUELLEN.md). Schlüssel = INKAR-Kürzel. Einige Kürzel gibt es in INKAR
 * unter zwei IDs (verschiedene Themenbereiche, gleiche Werte) — `id` legt
 * fest, welche importiert wird.
 *
 * ebene:  GEM = je Gemeinde (genauer als alles, was die Seite sonst pro Kreis
 *         zeigt), KRS = je Kreis
 * besser: 'hoch' / 'niedrig' / null — nur für die Einfärbung im Vergleich
 *         zum Bundes-Median, keine Wertung im Text
 * Keine Wahlergebnisse (Entscheidung User 14.10.2026).
 */
final class InkarIndikatoren
{
    public const GRUPPEN = [
        'nahversorgung' => 'Nahversorgung zu Fuß',
        'erreichbarkeit' => 'Mit dem Auto erreichbar',
        'leben' => 'Bevölkerung & Pendeln',
        'gruen' => 'Grün & Verkehrssicherheit',
        'gesundheit' => 'Gesundheit & Betreuung (Kreis)',
        'wohnen' => 'Wohnen & Mobilität (Kreis)',
    ];

    public const LISTE = [
        // Nahversorgung zu Fuß (Gemeinde, bevölkerungsgewichtet auf 100-m-Raster)
        'm_Q07_HA_DIST'   => ['id' => 7794,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'Hausarzt: Ø Fußweg', 'einheit' => 'm', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Durchschnittlicher Fußweg der Einwohner zur nächsten Hausarztpraxis (entlang begehbarer Wege, 100-m-Raster).'],
        'a_Q07_HA_ANT'    => ['id' => 7795,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'Hausarzt ≤ 1 km', 'einheit' => '%', 'stellen' => 0, 'besser' => 'hoch',
                              'info' => 'Anteil der Einwohner mit höchstens 1 km bis zur nächsten Hausarztpraxis.'],
        'm_Q01_APO_DIST'  => ['id' => 6328,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'Apotheke: Ø Fußweg', 'einheit' => 'm', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Durchschnittlicher Fußweg der Einwohner zur nächsten Apotheke (entlang begehbarer Wege, 100-m-Raster).'],
        'a_Q01_APO_ANT'   => ['id' => 7792,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'Apotheke ≤ 1 km', 'einheit' => '%', 'stellen' => 0, 'besser' => 'hoch',
                              'info' => 'Anteil der Einwohner mit höchstens 1 km bis zur nächsten Apotheke.'],
        'm_P01_PRIM_DIST' => ['id' => 6329,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'Grundschule: Ø Fußweg', 'einheit' => 'm', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Durchschnittlicher Fußweg der Einwohner zur nächsten Grundschule (entlang begehbarer Wege, 100-m-Raster).'],
        'a_P01_PRIM_ANT'  => ['id' => 7789,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'Grundschule ≤ 1 km', 'einheit' => '%', 'stellen' => 0, 'besser' => 'hoch',
                              'info' => 'Anteil der Einwohner mit höchstens 1 km bis zur nächsten Grundschule.'],
        'm_G02_SUP_DIST'  => ['id' => 6326,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'Supermarkt: Ø Fußweg', 'einheit' => 'm', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Durchschnittlicher Fußweg der Einwohner zum nächsten Supermarkt oder Discounter (entlang begehbarer Wege, 100-m-Raster).'],
        'a_G02_SUP_ANT'   => ['id' => 7786,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'Supermarkt ≤ 1 km', 'einheit' => '%', 'stellen' => 0, 'besser' => 'hoch',
                              'info' => 'Anteil der Einwohner mit höchstens 1 km bis zum nächsten Supermarkt oder Discounter.'],
        'm_OEV20_DIST'    => ['id' => 8750,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'ÖV-Haltestelle: Ø Fußweg', 'einheit' => 'm', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Durchschnittlicher Fußweg zur nächsten Haltestelle des öffentlichen Verkehrs mit mindestens 20 Abfahrten an Werktagen (entlang begehbarer Wege).'],
        'a_OEV20_ANT'     => ['id' => 8753,  'ebene' => 'GEM', 'gruppe' => 'nahversorgung', 'label' => 'ÖV-Haltestelle ≤ 1 km', 'einheit' => '%', 'stellen' => 0, 'besser' => 'hoch',
                              'info' => 'Anteil der Einwohner mit höchstens 1 km bis zur nächsten Haltestelle (mit mindestens 20 Abfahrten an Werktagen).'],

        // Mit dem Auto erreichbar (Gemeinde)
        'm_ErrBAB_bev'    => ['id' => 23012, 'ebene' => 'GEM', 'gruppe' => 'erreichbarkeit', 'label' => 'Autobahn-Anschluss', 'einheit' => 'Min.', 'stellen' => 0, 'besser' => null,
                              'info' => 'Pkw-Fahrzeit zur nächsten Autobahn-Anschlussstelle (bevölkerungsgewichteter Durchschnitt).'],
        'm_ErrIC_bev'     => ['id' => 23011, 'ebene' => 'GEM', 'gruppe' => 'erreichbarkeit', 'label' => 'Fernbahnhof (IC/EC/ICE)', 'einheit' => 'Min.', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Pkw-Fahrzeit zum nächsten Bahnhof mit Fernverkehr.'],
        'm_ErrAir_bev'    => ['id' => 23010, 'ebene' => 'GEM', 'gruppe' => 'erreichbarkeit', 'label' => 'Internationaler Flughafen', 'einheit' => 'Min.', 'stellen' => 0, 'besser' => null,
                              'info' => 'Pkw-Fahrzeit zum nächsten internationalen Flughafen.'],
        'i_fz_oz'         => ['id' => 18811, 'ebene' => 'GEM', 'gruppe' => 'erreichbarkeit', 'label' => 'Nächstes Oberzentrum', 'einheit' => 'Min.', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Pkw-Fahrzeit zum nächsten Oberzentrum (Großstadt mit Uni-Klinik, Theater, Fachgeschäften usw.).'],

        // Bevölkerung & Pendeln (Gemeinde)
        'm_bev_alter'     => ['id' => 16933, 'ebene' => 'GEM', 'gruppe' => 'leben', 'label' => 'Durchschnittsalter', 'einheit' => 'Jahre', 'stellen' => 1, 'besser' => null,
                              'info' => 'Durchschnittliches Lebensalter der Einwohner.'],
        'a_hh_kind'       => ['id' => 17800, 'ebene' => 'GEM', 'gruppe' => 'leben', 'label' => 'Haushalte mit Kindern', 'einheit' => '%', 'stellen' => 0, 'besser' => null,
                              'info' => 'Anteil der Haushalte, in denen Kinder leben.'],
        'q_kaufkraft'     => ['id' => 17707, 'ebene' => 'GEM', 'gruppe' => 'leben', 'label' => 'Kaufkraft je Einwohner', 'einheit' => '€', 'stellen' => 0, 'besser' => null,
                              'info' => 'Verfügbares Einkommen je Einwohner und Jahr (inkl. Renten und Transferleistungen, nach Steuern und Sozialbeiträgen). Quelle der Basisdaten: Nexiga.'],
        'q_pendlersaldo'  => ['id' => 17606, 'ebene' => 'GEM', 'gruppe' => 'leben', 'label' => 'Pendlersaldo', 'einheit' => 'je 100 Beschäftigte', 'stellen' => 0, 'besser' => null,
                              'info' => 'Einpendler minus Auspendler je 100 Beschäftigte am Arbeitsort. Positiv = Arbeitsort, negativ = Wohnort.'],
        'a_pend50'        => ['id' => 17261, 'ebene' => 'GEM', 'gruppe' => 'leben', 'label' => 'Pendelweg ≥ 50 km', 'einheit' => '%', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Anteil der Beschäftigten mit Wohnort hier, die 50 km oder mehr zur Arbeit pendeln.'],

        // Grün & Verkehrssicherheit (Gemeinde)
        'q_erhfl_bev'     => ['id' => 15008, 'ebene' => 'GEM', 'gruppe' => 'gruen', 'label' => 'Erholungsfläche je Einwohner', 'einheit' => 'm²', 'stellen' => 0, 'besser' => 'hoch',
                              'info' => 'Parks, Grünanlagen, Sport- und Freizeitflächen je Einwohner.'],
        'a_wald'          => ['id' => 14668, 'ebene' => 'GEM', 'gruppe' => 'gruen', 'label' => 'Waldanteil', 'einheit' => '%', 'stellen' => 0, 'besser' => null,
                              'info' => 'Anteil von Wald- und Gehölzflächen an der Gemeindefläche.'],
        'q_unf_bev'       => ['id' => 16567, 'ebene' => 'GEM', 'gruppe' => 'gruen', 'label' => 'Verkehrsunfälle', 'einheit' => 'je 100.000 Einw.', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Von der Polizei erfasste Unfälle mit Personenschaden je 100.000 Einwohner. Viel Durchgangsverkehr erhöht den Wert.'],
        'q_vunp_bev'      => ['id' => 16586, 'ebene' => 'GEM', 'gruppe' => 'gruen', 'label' => 'Verunglückte im Verkehr', 'einheit' => 'je 100.000 Einw.', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Verletzte und Getötete bei Verkehrsunfällen je 100.000 Einwohner.'],

        // Gesundheit & Betreuung (Kreis)
        'q_hausarzt_bev'      => ['id' => 16226, 'ebene' => 'KRS', 'gruppe' => 'gesundheit', 'label' => 'Hausärzte', 'einheit' => 'je 10.000 Einw.', 'stellen' => 1, 'besser' => 'hoch',
                                  'info' => 'Hausärztlich tätige Allgemeinmediziner (inkl. Angestellte) je 10.000 Einwohner. Quelle der Basisdaten: KBV.'],
        'q_kinderarzt_kinder' => ['id' => 16227, 'ebene' => 'KRS', 'gruppe' => 'gesundheit', 'label' => 'Kinderärzte', 'einheit' => 'je 10.000 Kinder', 'stellen' => 1, 'besser' => 'hoch',
                                  'info' => 'Kinderärzte (inkl. Angestellte) je 10.000 Kinder unter 15 Jahren.'],
        'q_kb_bev'            => ['id' => 155120, 'ebene' => 'KRS', 'gruppe' => 'gesundheit', 'label' => 'Krankenhausbetten', 'einheit' => 'je 1.000 Einw.', 'stellen' => 1, 'besser' => 'hoch',
                                  'info' => 'Aufgestellte Krankenhausbetten je 1.000 Einwohner.'],
        'q_pflpl_bev'         => ['id' => 14489, 'ebene' => 'KRS', 'gruppe' => 'gesundheit', 'label' => 'Pflegeheimplätze', 'einheit' => 'je 10.000 Einw.', 'stellen' => 0, 'besser' => 'hoch',
                                  'info' => 'Verfügbare stationäre Plätze in Pflegeheimen je 10.000 Einwohner.'],
        'a_betr0003_bev0003'  => ['id' => 19472, 'ebene' => 'KRS', 'gruppe' => 'gesundheit', 'label' => 'Kita-Quote unter 3 Jahren', 'einheit' => '%', 'stellen' => 0, 'besser' => 'hoch',
                                  'info' => 'Anteil der Kinder unter 3 Jahren in Kindertageseinrichtungen. Über 100 % möglich, wenn Kinder aus dem Umland hier betreut werden.'],
        'a_betr0306_bev0306'  => ['id' => 19473, 'ebene' => 'KRS', 'gruppe' => 'gesundheit', 'label' => 'Kita-Quote 3–6 Jahre', 'einheit' => '%', 'stellen' => 0, 'besser' => 'hoch',
                                  'info' => 'Anteil der Kinder von 3 bis unter 6 Jahren in Kindertageseinrichtungen. Über 100 % möglich, wenn Kinder aus dem Umland hier betreut werden.'],

        // Wohnen & Mobilität (Kreis)
        'm_mietpr'        => ['id' => 20067, 'ebene' => 'KRS', 'gruppe' => 'wohnen', 'label' => 'Angebotsmiete', 'einheit' => '€/m²', 'stellen' => 2, 'besser' => null,
                              'info' => 'Durchschnittliche Angebotsmiete (nettokalt) aus Inseraten. Bestandsmieten liegen meist darunter.'],
        'd_KW_BL'         => ['id' => 15167, 'ebene' => 'KRS', 'gruppe' => 'wohnen', 'label' => 'Baulandpreis', 'einheit' => '€/m²', 'stellen' => 0, 'besser' => null,
                              'info' => 'Durchschnittlicher Kaufpreis für baureifes Land (Mittel aus zwei Jahren).'],
        'q_wohneinbr'     => ['id' => 11075, 'ebene' => 'KRS', 'gruppe' => 'wohnen', 'label' => 'Wohnungseinbrüche', 'einheit' => 'je 100.000 Einw.', 'stellen' => 0, 'besser' => 'niedrig',
                              'info' => 'Erfasste Wohnungseinbruchdiebstähle je 100.000 Einwohner (PKS).'],
        'q_pkw_bev'       => ['id' => 16566, 'ebene' => 'KRS', 'gruppe' => 'wohnen', 'label' => 'Pkw-Dichte', 'einheit' => 'je 1.000 Einw.', 'stellen' => 0, 'besser' => null,
                              'info' => 'Zugelassene Pkw je 1.000 Einwohner.'],
        'q_ladepunkte'    => ['id' => 17962, 'ebene' => 'KRS', 'gruppe' => 'wohnen', 'label' => 'Öffentliche Ladepunkte', 'einheit' => 'je 100.000 Einw.', 'stellen' => 0, 'besser' => 'hoch',
                              'info' => 'Öffentlich zugängliche Ladepunkte für E-Autos je 100.000 Einwohner.'],
    ];
}
