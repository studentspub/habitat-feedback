<?php

namespace App\Support;

final class PoiCategories
{
    /**
     * Kategorie -> OSM-Tag-Filter. Für den einmaligen Massen-Import (anders
     * als beim früheren Live-Ansatz) lohnt sich `way` zusätzlich zu `node`:
     * größere Objekte wie Supermärkte oder Universitätsgelände sind in OSM
     * oft als Gebäudeumriss (way) statt als einzelner Punkt getaggt, und ein
     * einmaliger Import kann sich die teurere Geometrieauflösung leisten,
     * die wir bei Live-Anfragen bewusst weggelassen hatten.
     *
     * Jeder Eintrag ist ein Overpass-Filterausdruck, wie er zwischen die
     * eckigen Klammern von `node[...]` gehört. Ein Eintrag darf deshalb auch
     * mehrere Bedingungen tragen (`a=b][c!~"..."`), siehe toilets und ebike
     * (Issue #1): so bleiben Ausschlüsse im Filter statt im Skript.
     */
    public const CATEGORIES = [
        'playground'  => ['leisure=playground'],
        'food_drink'  => ['amenity=bar', 'amenity=pub', 'amenity=cafe', 'amenity=restaurant', 'amenity=biergarten'],
        'leisure'     => ['leisure=fitness_centre', 'leisure=sports_centre', 'leisure=swimming_pool', 'amenity=cinema'],
        'university'  => ['amenity=university', 'amenity=college'],
        'school'      => ['amenity=school', 'amenity=kindergarten'],
        'doctor'      => ['amenity=doctors', 'amenity=pharmacy'],
        'supermarket' => ['shop=supermarket'],
        'transit'     => ['highway=bus_stop', 'railway=tram_stop', 'railway=station'],
        // Issue #1: öffentliche Toiletten ohne die von Gaststätten/Firmen (access=private/customers/no, ~20 %).
        'toilets'     => ['amenity=toilets][access!~"^(private|customers|no)$"'],
        // Issue #1: Briefkästen, Packstationen aller Anbieter, Postfilialen und Paketshops (in OSM alle post_office).
        'post'        => ['amenity=post_box', 'amenity=parcel_locker', 'amenity=post_office'],
        // Issue #1: Ladestationen, an denen Fahrräder laden dürfen; die Auto-Ladesäulen kommen von der BNetzA (#22).
        'ebike'       => ['amenity=charging_station][bicycle~"^(yes|designated|only)$"'],
    ];

    /**
     * Kategorien aus anderen Quellen als OSM — liegen ebenfalls in
     * poi_locations, werden aber nicht von bin/import_poi.php befüllt.
     */
    public const EXTERN = [
        'charging' => 'Ladesäulenregister der Bundesnetzagentur (bin/import_ladesaeulen.php, Issue #22)',
    ];

    /** @return list<string> alle Kategorien, die /api/poi ausliefert */
    public static function alle(): array
    {
        return array_merge(array_keys(self::CATEGORIES), array_keys(self::EXTERN));
    }

    /**
     * Beschriftung für den Tooltip (Spalte name). Bei den alten Kategorien
     * der OSM-Name, bei den neuen aus Issue #1 eine Art-Angabe, weil ein
     * Briefkasten oder eine Toilette in OSM meist keinen Namen hat:
     * „Toilette · barrierefrei · kostenlos“, „Packstation (DHL Packstation)“,
     * „Postfiliale/Paketshop (Hermes)“, „E-Bike-Ladestation (Stadtwerke)“.
     *
     * @param array<string, string> $tags OSM-Tags des Elements
     */
    public static function beschriftung(string $category, array $tags): ?string
    {
        $name = isset($tags['name']) ? trim($tags['name']) : '';
        $marke = trim($tags['brand'] ?? $tags['operator'] ?? '');
        $mitZusatz = static fn(string $art, string $zusatz): string => $zusatz !== '' && $zusatz !== $art ? "$art ($zusatz)" : $art;

        switch ($category) {
            case 'toilets':
                $teile = ['Toilette'];
                if (self::barrierefrei($tags)) {
                    $teile[] = 'barrierefrei';
                }
                $fee = $tags['fee'] ?? '';
                if ($fee === 'no') {
                    $teile[] = 'kostenlos';
                } elseif ($fee === 'yes') {
                    $teile[] = 'gebührenpflichtig';
                }
                $text = implode(' · ', $teile);
                return $name !== '' ? "$name: $text" : $text;

            case 'post':
                switch ($tags['amenity'] ?? '') {
                    case 'post_box':
                        return 'Briefkasten' . (isset($tags['collection_times']) ? ' · Leerung ' . $tags['collection_times'] : '');
                    case 'parcel_locker':
                        return $mitZusatz('Packstation', $marke !== '' ? $marke : $name);
                    default:
                        $art = ($tags['post_office'] ?? '') === 'post_partner' ? 'Post-Partnerfiliale' : 'Postfiliale/Paketshop';
                        return $mitZusatz($art, $marke !== '' ? $marke : $name);
                }

            case 'ebike':
                return $mitZusatz('E-Bike-Ladestation', $name !== '' ? $name : $marke);

            default:
                return $name !== '' ? $name : null;
        }
    }

    /** Rollstuhlgerecht laut OSM (wheelchair bzw. toilets:wheelchair = yes/designated), Issue #1. */
    public static function barrierefrei(array $tags): bool
    {
        foreach (['wheelchair', 'toilets:wheelchair'] as $k) {
            if (in_array($tags[$k] ?? '', ['yes', 'designated'], true)) {
                return true;
            }
        }
        return false;
    }
}
