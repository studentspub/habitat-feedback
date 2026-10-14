<?php

namespace App\Support;

/**
 * Vorschläge für Adressfelder aus dem lokalen Straßenverzeichnis (Issue #15,
 * Tabelle `strassen`, befüllt von bin/import_strassen.php aus OpenStreetMap).
 * Ersetzt die Live-Abfrage bei Nominatim beim Tippen: Tastenanschläge
 * verlassen den Server nicht mehr, Nominatim wird nur noch einmal beim
 * Absenden einer Bewertung gefragt (Geocode-Worker).
 *
 * Eine Zeile je Straße + PLZ + Ort, ohne Hausnummern. Die Hausnummer, die
 * jemand mittippt ("Kantstr 12 Berlin"), wird erkannt und separat
 * zurückgegeben.
 */
final class StrassenSuche
{
    public const MAX_TREFFER = 8;
    private const MAX_ORTE = 3;
    private const MAX_WOERTER = 6;

    private const ZEICHEN = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
        'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'č' => 'c', 'ć' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
        'ë' => 'e', 'ě' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ł' => 'l', 'ñ' => 'n',
        'ń' => 'n', 'ň' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ř' => 'r', 'ś' => 's', 'š' => 's', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ů' => 'u', 'ý' => 'y',
        'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
    ];

    /**
     * Suchschlüssel: klein, Umlaute ausgeschrieben, "straße"/"strasse"/"str." -> "str",
     * alles außer a-z und 0-9 entfernt. "Karl-Marx-Straße" und "karl marx str."
     * ergeben beide "karlmarxstr". Import und Suche benutzen dieselbe Funktion.
     */
    public static function norm(string $s): string
    {
        $s = strtr(mb_strtolower($s, 'UTF-8'), self::ZEICHEN);
        $s = str_replace('strasse', 'str', $s);
        return preg_replace('/[^a-z0-9]+/', '', $s);
    }

    /**
     * @return array{results: list<array<string, mixed>>, hausnummer: ?string}
     *   results: art "ort" (nur mit $mitOrten, Gemeinden aus VG250, mit bbox) und art "strasse"
     */
    public static function suchen(string $q, bool $mitOrten): array
    {
        [$woerter, $rest, $plz, $hausnummer, $mitKomma] = self::zerlegen($q);

        $orte = [];
        if ($mitOrten && $hausnummer === null && $plz === null && !$mitKomma && !preg_match('/\d/', $q)) {
            $orte = self::orte(trim($q));
        }

        $strassen = [];
        $limit = self::MAX_TREFFER - count($orte);
        // Mit PLZ zuerst; passt sie zu keiner Straße (vertippt, alte PLZ), ohne PLZ weitersuchen.
        foreach (array_unique([$plz, null], SORT_REGULAR) as $p) {
            foreach (self::aufteilungen($woerter, $rest, $mitKomma) as [$s, $o]) {
                $strassen = self::strassen($s, $o, $p, $limit);
                if ($strassen !== []) {
                    break 2;
                }
            }
        }
        return ['results' => array_merge($orte, $strassen), 'hausnummer' => $hausnummer];
    }

    /**
     * Zerlegt die Eingabe in Straßenwörter, Ortswörter, PLZ und Hausnummer.
     * "Kantstraße 12, 10623 Berlin" -> [Kantstraße], [Berlin], 10623, 12.
     * Eine Zahl mit Punkt ("Straße des 17. Juni") ist keine Hausnummer.
     */
    private static function zerlegen(string $q): array
    {
        $teile = explode(',', $q, 2);
        $mitKomma = count($teile) === 2;
        $woerter = preg_split('/\s+/u', trim($teile[0]), -1, PREG_SPLIT_NO_EMPTY);
        $rest = $mitKomma ? preg_split('/[\s,]+/u', trim($teile[1]), -1, PREG_SPLIT_NO_EMPTY) : [];

        $plz = null;
        foreach ([&$woerter, &$rest] as &$liste) {
            foreach ($liste as $i => $w) {
                if (preg_match('/^\d{5}$/', $w)) {
                    $plz = $w;
                    unset($liste[$i]);
                }
            }
            $liste = array_values($liste);
        }
        unset($liste);

        $hausnummer = null;
        foreach ($woerter as $i => $w) {
            if ($i > 0 && preg_match('/^\d{1,4}[a-zA-Z]?(?:[-\/]\d{1,4}[a-zA-Z]?)?$/', $w)) {
                $hausnummer = $w;
                $rest = array_merge(array_slice($woerter, $i + 1), $rest);
                $woerter = array_slice($woerter, 0, $i);
                $mitKomma = $mitKomma || $rest !== [];
                break;
            }
        }
        return [array_slice($woerter, 0, self::MAX_WOERTER), $rest, $plz, $hausnummer, $mitKomma];
    }

    /**
     * Mögliche Trennungen in Straße und Ort, die wahrscheinlichste zuerst. Ohne
     * Komma und Hausnummer ist offen, wo die Straße endet ("Kantstraße Berlin"):
     * erst alles als Straße, dann das letzte Wort als Ort usw., dann der Ort
     * vorn ("Leipzig Hauptstraße"), zuletzt nur die Straße ohne Ortsfilter
     * (Ort falsch geschrieben oder ein Ortsteil).
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function aufteilungen(array $woerter, array $rest, bool $mitKomma): array
    {
        $n = count($woerter);
        $liste = [];
        if ($mitKomma) {
            $liste[] = [implode(' ', $woerter), implode(' ', $rest)];
        } else {
            for ($k = $n; $k >= 1; $k--) {
                $liste[] = [implode(' ', array_slice($woerter, 0, $k)), implode(' ', array_slice($woerter, $k))];
            }
            for ($k = 1; $k < $n; $k++) {
                $liste[] = [implode(' ', array_slice($woerter, $k)), implode(' ', array_slice($woerter, 0, $k))];
            }
        }
        for ($k = $n; $k >= 1; $k--) {
            $liste[] = [implode(' ', array_slice($woerter, 0, $k)), ''];
        }

        $aufteilungen = [];
        foreach ($liste as [$s, $o]) {
            // Angefangenes "Straße" am Ende ("Hauptstra") passt sonst nicht zu "hauptstr".
            $s = preg_replace('/str(a|as|ass)$/', 'str', self::norm($s));
            if (strlen($s) >= 3) {
                $aufteilungen[$s . '|' . self::norm($o)] = [$s, self::norm($o)];
            }
        }
        return array_values($aufteilungen);
    }

    private static function strassen(string $s, string $o, ?string $plz, int $limit): array
    {
        $params = [$s, $s . '%'];
        $where = 'name_norm LIKE ?';
        if ($o !== '') {
            $where .= ' AND ort_norm LIKE ?';
            $params[] = $o . '%';
        }
        if ($plz !== null) {
            $where .= ' AND plz = ?';
            $params[] = $plz;
        }
        // Genauer Name zuerst, dann belebte Straßen (viele Adressen) vor kleinen. Die innere
        // Abfrage läuft nur über den Index idx_suche, erst die Treffer werden ganz gelesen.
        $stmt = Db::get()->prepare(
            'SELECT s.name, s.plz, s.ort, s.lat, s.lon
             FROM (SELECT id, name_norm = ? AS genau, adressen FROM strassen WHERE ' . $where . '
                   ORDER BY genau DESC, adressen DESC LIMIT ' . max(1, $limit) . ') t
             JOIN strassen s ON s.id = t.id
             ORDER BY t.genau DESC, t.adressen DESC'
        );
        $stmt->execute($params);

        return array_map(static fn(array $r): array => [
            'art' => 'strasse',
            'label' => $r['name'] . ', ' . ($r['plz'] !== null ? $r['plz'] . ' ' : '') . $r['ort'],
            'strasse' => $r['name'],
            'plz' => $r['plz'],
            'ort' => $r['ort'],
            'lat' => (float) $r['lat'],
            'lon' => (float) $r['lon'],
        ], $stmt->fetchAll());
    }

    /** Gemeinden aus VG250 (Issue #17) für das Kartenfeld „Adresse finden“: Name, Mitte, Umriss-Rechteck. */
    private static function orte(string $q): array
    {
        if (mb_strlen($q) < 3) {
            return [];
        }
        $stmt = Db::get()->prepare(
            'SELECT g.name, k.name AS kreis, k.bez AS kreis_bez,
                    ST_X(ST_Centroid(g.geom)) AS lon, ST_Y(ST_Centroid(g.geom)) AS lat,
                    ST_AsText(ST_Envelope(g.geom)) AS rahmen
             FROM gebiete_gemeinde g JOIN gebiete_kreis k ON k.ags = g.kreis_ags
             WHERE g.name LIKE ?
             ORDER BY g.name = ? DESC, CHAR_LENGTH(g.name), g.name
             LIMIT ' . self::MAX_ORTE
        );
        $stmt->execute([addcslashes($q, '%_\\') . '%', $q]);

        $orte = [];
        foreach ($stmt->fetchAll() as $r) {
            preg_match_all('/-?\d+(?:\.\d+)?/', $r['rahmen'], $m);
            $x = array_map('floatval', array_slice($m[0], 0, 10));
            $zusatz = $r['kreis'] === $r['name'] ? $r['kreis_bez'] : $r['kreis_bez'] . ' ' . $r['kreis'];
            $orte[] = [
                'art' => 'ort',
                'label' => $r['name'] . ' (' . $zusatz . ')',
                'lat' => round((float) $r['lat'], 5),
                'lon' => round((float) $r['lon'], 5),
                // [minLon, minLat, maxLon, maxLat] aus den Eckpunkten des Rechtecks
                'bbox' => [min($x[0], $x[4]), min($x[1], $x[5]), max($x[0], $x[4]), max($x[1], $x[5])],
            ];
        }
        return $orte;
    }
}
