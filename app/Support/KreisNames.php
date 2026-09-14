<?php

namespace App\Support;

final class KreisNames
{
    /**
     * Amtliche Kreis-Namen, wie sie die Statistik der Bundesagentur für
     * Arbeit als Wert des Parameters "Kreis=" in ihrer API erwartet (siehe
     * bin/import_unemployment.php). Einmalig aus der öffentlichen
     * Regionen-Auswahlliste der Statistik-Website extrahiert (401 Kreise/
     * kreisfreie Städte, Stand 2026) — Kreisgrenzen ändern sich in
     * Deutschland so selten (zuletzt große Reform 1994, seither nur
     * vereinzelte Zusammenlegungen), dass eine feste Liste hier sinnvoller
     * ist als sie bei jedem Import erneut von einer internen, nicht als API
     * dokumentierten JS-Datei zu scrapen.
     */
    private const NAMES = [
    'Ahrweiler',
    'Aichach-Friedberg',
    'Alb-Donau-Kreis',
    'Altenburger Land',
    'Altenkirchen (Westerwald)',
    'Altmarkkreis Salzwedel',
    'Altötting',
    'Alzey-Worms',
    'Amberg, Stadt',
    'Amberg-Sulzbach',
    'Ammerland',
    'Anhalt-Bitterfeld',
    'Ansbach',
    'Ansbach, Stadt',
    'Aschaffenburg',
    'Aschaffenburg, Stadt',
    'Augsburg',
    'Augsburg, Stadt',
    'Aurich',
    'Bad Dürkheim',
    'Bad Kissingen',
    'Bad Kreuznach',
    'Bad Tölz-Wolfratshausen',
    'Baden-Baden, Stadt',
    'Bamberg',
    'Bamberg, Stadt',
    'Barnim',
    'Bautzen',
    'Bayreuth',
    'Bayreuth, Stadt',
    'Berchtesgadener Land',
    'Bergstraße',
    'Berlin, Stadt',
    'Bernkastel-Wittlich',
    'Biberach',
    'Bielefeld, Stadt',
    'Birkenfeld',
    'Bochum, Stadt',
    'Bodenseekreis',
    'Bonn, Stadt',
    'Borken',
    'Bottrop, Stadt',
    'Brandenburg an der Havel, Stadt',
    'Braunschweig, Stadt',
    'Breisgau-Hochschwarzwald',
    'Bremen, Stadt',
    'Bremerhaven, Stadt',
    'Burgenlandkreis',
    'Böblingen',
    'Börde',
    'Calw',
    'Celle',
    'Cham',
    'Chemnitz, Stadt',
    'Cloppenburg',
    'Coburg',
    'Coburg, Stadt',
    'Cochem-Zell',
    'Coesfeld',
    'Cottbus, Stadt',
    'Cuxhaven',
    'Dachau',
    'Dahme-Spreewald',
    'Darmstadt, Wissenschaftsstadt',
    'Darmstadt-Dieburg',
    'Deggendorf',
    'Delmenhorst, Stadt',
    'Dessau-Roßlau, Stadt',
    'Diepholz',
    'Dillingen a.d.Donau',
    'Dingolfing-Landau',
    'Dithmarschen',
    'Donau-Ries',
    'Donnersbergkreis',
    'Dortmund, Stadt',
    'Dresden, Stadt',
    'Duisburg, Stadt',
    'Düren',
    'Düsseldorf, Stadt',
    'Ebersberg',
    'Eichsfeld',
    'Eichstätt',
    'Eifelkreis Bitburg-Prüm',
    'Elbe-Elster',
    'Emden, Stadt',
    'Emmendingen',
    'Emsland',
    'Ennepe-Ruhr-Kreis',
    'Enzkreis',
    'Erding',
    'Erfurt, Stadt',
    'Erlangen, Stadt',
    'Erlangen-Höchstadt',
    'Erzgebirgskreis',
    'Essen, Stadt',
    'Esslingen',
    'Euskirchen',
    'Flensburg, Stadt',
    'Forchheim',
    'Frankenthal (Pfalz), kreisfreie Stadt',
    'Frankfurt (Oder), Stadt',
    'Frankfurt am Main, Stadt',
    'Freiburg im Breisgau, Stadt',
    'Freising',
    'Freudenstadt',
    'Freyung-Grafenau',
    'Friesland',
    'Fulda',
    'Fürstenfeldbruck',
    'Fürth',
    'Fürth, Stadt',
    'Garmisch-Partenkirchen',
    'Gelsenkirchen, Stadt',
    'Gera, Stadt',
    'Germersheim',
    'Gießen',
    'Gifhorn',
    'Goslar',
    'Gotha',
    'Grafschaft Bentheim',
    'Greiz',
    'Groß-Gerau',
    'Göppingen',
    'Görlitz',
    'Göttingen',
    'Günzburg',
    'Gütersloh',
    'Hagen, Stadt der FernUniversität',
    'Halle (Saale), Stadt',
    'Hamburg, Freie und Hansestadt',
    'Hameln-Pyrmont',
    'Hamm, Stadt',
    'Hanau, Brüder-Grimm-Stadt',
    'Harburg',
    'Harz',
    'Havelland',
    'Haßberge',
    'Heidekreis',
    'Heidelberg, Stadt',
    'Heidenheim',
    'Heilbronn',
    'Heilbronn, Universitätsstadt',
    'Heinsberg',
    'Helmstedt',
    'Herford',
    'Herne, Stadt',
    'Hersfeld-Rotenburg',
    'Herzogtum Lauenburg',
    'Hildburghausen',
    'Hildesheim',
    'Hochsauerlandkreis',
    'Hochtaunuskreis',
    'Hof',
    'Hof, Stadt',
    'Hohenlohekreis',
    'Holzminden',
    'Höxter',
    'Ilm-Kreis',
    'Ingolstadt, Stadt',
    'Jena, Stadt',
    'Jerichower Land',
    'Kaiserslautern',
    'Kaiserslautern, kreisfreie Stadt',
    'Karlsruhe',
    'Karlsruhe, Stadt',
    'Kassel',
    'Kassel, documenta-Stadt',
    'Kaufbeuren, Stadt',
    'Kelheim',
    'Kempten (Allgäu), Stadt',
    'Kiel, Landeshauptstadt',
    'Kitzingen',
    'Kleve',
    'Koblenz, kreisfreie Stadt',
    'Konstanz',
    'Krefeld, Stadt',
    'Kronach',
    'Kulmbach',
    'Kusel',
    'Kyffhäuserkreis',
    'Köln, Stadt',
    'Lahn-Dill-Kreis',
    'Landau in der Pfalz, kreisfreie Stadt',
    'Landkreis Rostock',
    'Landsberg am Lech',
    'Landshut',
    'Landshut, Stadt',
    'Leer',
    'Leipzig',
    'Leipzig, Stadt',
    'Leverkusen, Stadt',
    'Lichtenfels',
    'Limburg-Weilburg',
    'Lindau (Bodensee)',
    'Lippe',
    'Ludwigsburg',
    'Ludwigshafen am Rhein, kreisfreie Stadt',
    'Ludwigslust-Parchim',
    'Lörrach',
    'Lübeck, Hansestadt',
    'Lüchow-Dannenberg',
    'Lüneburg',
    'Magdeburg, Landeshauptstadt',
    'Main-Kinzig-Kreis',
    'Main-Spessart',
    'Main-Tauber-Kreis',
    'Main-Taunus-Kreis',
    'Mainz, kreisfreie Stadt',
    'Mainz-Bingen',
    'Mannheim, Universitätsstadt',
    'Mansfeld-Südharz',
    'Marburg-Biedenkopf',
    'Mayen-Koblenz',
    'Mecklenburgische Seenplatte',
    'Meißen',
    'Memmingen, Stadt',
    'Merzig-Wadern',
    'Mettmann',
    'Miesbach',
    'Miltenberg',
    'Minden-Lübbecke',
    'Mittelsachsen',
    'Märkisch-Oderland',
    'Märkischer Kreis',
    'Mönchengladbach, Stadt',
    'Mühldorf a.Inn',
    'Mülheim an der Ruhr, Stadt',
    'München',
    'München, Landeshauptstadt',
    'Münster, Stadt',
    'Neckar-Odenwald-Kreis',
    'Neu-Ulm',
    'Neuburg-Schrobenhausen',
    'Neumarkt i.d.OPf.',
    'Neumünster, Stadt',
    'Neunkirchen',
    'Neustadt a.d.Aisch-Bad Windsheim',
    'Neustadt a.d.Waldnaab',
    'Neustadt an der Weinstraße, kreisfreie Stadt',
    'Neuwied',
    'Nienburg (Weser)',
    'Nordfriesland',
    'Nordhausen',
    'Nordsachsen',
    'Nordwestmecklenburg',
    'Northeim',
    'Nürnberg, Stadt',
    'Nürnberger Land',
    'Oberallgäu',
    'Oberbergischer Kreis',
    'Oberhausen, Stadt',
    'Oberhavel',
    'Oberspreewald-Lausitz',
    'Odenwaldkreis',
    'Oder-Spree',
    'Offenbach',
    'Offenbach am Main, Stadt',
    'Oldenburg',
    'Oldenburg (Oldb), Stadt',
    'Olpe',
    'Ortenaukreis',
    'Osnabrück',
    'Osnabrück, Stadt',
    'Ostalbkreis',
    'Ostallgäu',
    'Osterholz',
    'Ostholstein',
    'Ostprignitz-Ruppin',
    'Paderborn',
    'Passau',
    'Passau, Stadt',
    'Peine',
    'Pfaffenhofen a.d.Ilm',
    'Pforzheim, Stadt',
    'Pinneberg',
    'Pirmasens, kreisfreie Stadt',
    'Plön',
    'Potsdam, Stadt',
    'Potsdam-Mittelmark',
    'Prignitz',
    'Rastatt',
    'Ravensburg',
    'Recklinghausen',
    'Regen',
    'Regensburg',
    'Regensburg, Stadt',
    'Region Hannover',
    'Regionalverband Saarbrücken',
    'Rems-Murr-Kreis',
    'Remscheid, Stadt',
    'Rendsburg-Eckernförde',
    'Reutlingen',
    'Rhein-Erft-Kreis',
    'Rhein-Hunsrück-Kreis',
    'Rhein-Kreis Neuss',
    'Rhein-Lahn-Kreis',
    'Rhein-Neckar-Kreis',
    'Rhein-Pfalz-Kreis',
    'Rhein-Sieg-Kreis',
    'Rheingau-Taunus-Kreis',
    'Rheinisch-Bergischer Kreis',
    'Rhön-Grabfeld',
    'Rosenheim',
    'Rosenheim, Stadt',
    'Rostock, Hanse- und Universitätsstadt',
    'Rotenburg (Wümme)',
    'Roth',
    'Rottal-Inn',
    'Rottweil',
    'Saale-Holzland-Kreis',
    'Saale-Orla-Kreis',
    'Saalekreis',
    'Saalfeld-Rudolstadt',
    'Saarlouis',
    'Saarpfalz-Kreis',
    'Salzgitter, Stadt',
    'Salzlandkreis',
    'Schaumburg',
    'Schleswig-Flensburg',
    'Schmalkalden-Meiningen',
    'Schwabach, Stadt',
    'Schwalm-Eder-Kreis',
    'Schwandorf',
    'Schwarzwald-Baar-Kreis',
    'Schweinfurt',
    'Schweinfurt, Stadt',
    'Schwerin, Landeshauptstadt',
    'Schwäbisch Hall',
    'Segeberg',
    'Siegen-Wittgenstein',
    'Sigmaringen',
    'Soest',
    'Solingen, Klingenstadt',
    'Sonneberg',
    'Speyer, kreisfreie Stadt',
    'Spree-Neiße',
    'St. Wendel',
    'Stade',
    'Starnberg',
    'Steinburg',
    'Steinfurt',
    'Stendal',
    'Stormarn',
    'Straubing, Stadt',
    'Straubing-Bogen',
    'Stuttgart, Landeshauptstadt',
    'Städteregion Aachen',
    'Suhl, Stadt',
    'Sächsische Schweiz-Osterzgebirge',
    'Sömmerda',
    'Südliche Weinstraße',
    'Südwestpfalz',
    'Teltow-Fläming',
    'Tirschenreuth',
    'Traunstein',
    'Trier, kreisfreie Stadt',
    'Trier-Saarburg',
    'Tuttlingen',
    'Tübingen',
    'Uckermark',
    'Uelzen',
    'Ulm, Universitätsstadt',
    'Unna',
    'Unstrut-Hainich-Kreis',
    'Unterallgäu',
    'Vechta',
    'Verden',
    'Viersen',
    'Vogelsbergkreis',
    'Vogtlandkreis',
    'Vorpommern-Greifswald',
    'Vorpommern-Rügen',
    'Vulkaneifel',
    'Waldeck-Frankenberg',
    'Waldshut',
    'Warendorf',
    'Wartburgkreis',
    'Weiden i.d.OPf., Stadt',
    'Weilheim-Schongau',
    'Weimar, Stadt',
    'Weimarer Land',
    'Weißenburg-Gunzenhausen',
    'Werra-Meißner-Kreis',
    'Wesel',
    'Wesermarsch',
    'Westerwaldkreis',
    'Wetteraukreis',
    'Wiesbaden, Landeshauptstadt',
    'Wilhelmshaven, Stadt',
    'Wittenberg',
    'Wittmund',
    'Wolfenbüttel',
    'Wolfsburg, Stadt',
    'Worms, kreisfreie Stadt',
    'Wunsiedel i.Fichtelgebirge',
    'Wuppertal, Stadt',
    'Würzburg',
    'Würzburg, Stadt',
    'Zollernalbkreis',
    'Zweibrücken, kreisfreie Stadt',
    'Zwickau',
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        return self::NAMES;
    }

    /**
     * Ordnet einer Nominatim-Adresse (county/city aus addressdetails) den
     * passenden amtlichen Kreis-Namen zu.
     *
     * Kern des Problems: dieselbe Stadt taucht in unserer Kreis-Liste oft
     * zweimal auf — einmal als Landkreis, einmal als kreisfreie Stadt (z.B.
     * "München" vs. "München, Landeshauptstadt") — mit völlig uneinheitlichen
     * Zusätzen (", Stadt", ", Landeshauptstadt", ", kreisfreie Stadt",
     * ", Hanse- und Universitätsstadt" ...). Ein Vergleich nur des Namensteils
     * vor dem ersten Komma räumt das zuverlässig weg, ohne jeden einzelnen
     * Zusatz von Hand aufzählen zu müssen.
     *
     * Die Auflösung der verbleibenden Mehrdeutigkeit (Landkreis oder Stadt?)
     * nutzt dasselbe Signal, das Nominatim selbst schon liefert: Nominatim
     * füllt "county" nur für Orte in einem Landkreis, nie für eine
     * kreisfreie Stadt. Ist "county" gesetzt, ist also der Landkreis (die
     * Variante ohne Komma-Zusatz) gemeint; kommt nur "city"/"town" ohne
     * "county", ist es die kreisfreie Stadt (die Variante mit Zusatz).
     */
    public static function match(?string $county, ?string $city): ?string
    {
        if ($county !== null) {
            $county = trim($county);
            if (in_array($county, self::NAMES, true)) {
                return $county;
            }
            $withoutPrefix = preg_replace('/^Landkreis\s+/u', '', $county);
            if ($withoutPrefix !== null && in_array($withoutPrefix, self::NAMES, true)) {
                return $withoutPrefix;
            }

            $candidates = self::candidatesFor($county);
            if ($candidates !== []) {
                // county gesetzt -> Landkreis gemeint -> die Variante ohne
                // Komma-Zusatz bevorzugen, falls beide existieren.
                usort($candidates, static fn (string $a, string $b): int => (int) str_contains($a, ',') <=> (int) str_contains($b, ','));
                return $candidates[0];
            }
        }

        if ($city !== null) {
            $candidates = self::candidatesFor(trim($city));
            if ($candidates !== []) {
                // kein county -> kreisfreie Stadt gemeint -> die Variante MIT
                // Komma-Zusatz bevorzugen, falls beide existieren.
                usort($candidates, static fn (string $a, string $b): int => (int) str_contains($b, ',') <=> (int) str_contains($a, ','));
                return $candidates[0];
            }
        }

        return null;
    }

    /**
     * Regionale Gebietskörperschaften, die eine Kreisstadt komplett ersetzt
     * haben und dabei einen eigenen vorangestellten Namen bekamen (z.B. die
     * "Städteregion Aachen" seit der Kommunalreform 2009, die den früheren
     * "Kreis Aachen" ersetzt). Andere Datenquellen als die Arbeitsagentur
     * kennen diese Präfixe nicht immer und melden schlicht "Aachen" — ohne
     * diesen Fallback bliebe das ein Nicht-Treffer, obwohl die Zuordnung
     * eindeutig ist (in unserer Liste gibt es kein zweites "Aachen").
     */
    private const REGIONAL_AUTHORITY_PREFIXES = ['Städteregion', 'Region', 'Regionalverband'];

    /** @return array<int, string> */
    private static function candidatesFor(string $needle): array
    {
        $needle = self::normalize($needle);
        $matches = [];
        foreach (self::NAMES as $name) {
            if (self::normalize($name) === $needle) {
                $matches[] = $name;
            }
        }
        if ($matches === []) {
            foreach (self::REGIONAL_AUTHORITY_PREFIXES as $prefix) {
                $matches = array_merge($matches, self::candidatesForExact($prefix . ' ' . $needle));
            }
        }
        return $matches;
    }

    /** @return array<int, string> */
    private static function candidatesForExact(string $normalizedTarget): array
    {
        $matches = [];
        foreach (self::NAMES as $name) {
            if (self::normalize($name) === $normalizedTarget) {
                $matches[] = $name;
            }
        }
        return $matches;
    }

    private static function normalize(string $name): string
    {
        $beforeComma = explode(',', $name, 2)[0];
        $withoutParens = preg_replace('/\s*\([^)]*\)\s*$/u', '', $beforeComma);
        return trim($withoutParens ?? $beforeComma);
    }
}
