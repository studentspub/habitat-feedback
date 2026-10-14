<?php

namespace App\Support;

/**
 * Punkt -> Gemeinde und Kreis aus den lokalen VG250-Grenzen (Issue #17,
 * Tabellen gebiete_gemeinde / gebiete_kreis, bin/import_vg250.php).
 * Ersetzt die Nominatim-Rückwärtssuche: kein externer Dienst, keine
 * Drossel, und zusätzlich der amtliche Gemeindeschlüssel (AGS).
 */
final class GebietLookup
{
    /**
     * @return array{gemeinde_ags: string, gemeinde: string, gemeinde_bez: string,
     *               kreis_ags: string, kreis: string, kreis_bez: string, kreis_name: ?string}|null
     *         null, wenn der Punkt in keiner Gemeinde liegt (Ausland, Meer) oder die Tabellen leer sind
     */
    public static function fuerPunkt(float $lat, float $lon): ?array
    {
        $stmt = Db::get()->prepare(
            'SELECT g.ags AS gemeinde_ags, g.name AS gemeinde, g.bez AS gemeinde_bez,
                    k.ags AS kreis_ags, k.name AS kreis, k.bez AS kreis_bez, k.kreis_name
             FROM gebiete_gemeinde g
             JOIN gebiete_kreis k ON k.ags = g.kreis_ags
             WHERE ST_Contains(g.geom, ST_GeomFromText(:punkt))
             LIMIT 1'
        );
        try {
            $stmt->execute(['punkt' => sprintf('POINT(%.6F %.6F)', $lon, $lat)]);
        } catch (\PDOException $e) {
            return null; // Tabellen (noch) nicht angelegt -> Aufrufer nimmt den alten Weg
        }
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }
}
