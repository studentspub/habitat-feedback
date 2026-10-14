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
}
