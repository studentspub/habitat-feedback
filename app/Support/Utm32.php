<?php

namespace App\Support;

/**
 * Umrechnung ETRS89 / UTM Zone 32N (EPSG:25832) -> geographische Koordinaten
 * (ETRS89, für Kartenzwecke = WGS84). Amtliche Geodaten (BKG VG250,
 * Unfallatlas) liegen in UTM 32, Leaflet und unsere Tabellen in Grad.
 *
 * Krüger-Reihen 4. Ordnung (wie in Karney 2011 / Wikipedia „UTM, inverse"):
 * auch am Ostrand Deutschlands (≈ 6° vom Mittelmeridian 9° E entfernt)
 * besser als 1 mm — einfache Snyder-Formeln würden dort schon spürbar
 * abweichen.
 */
final class Utm32
{
    private const A = 6378137.0;              // GRS80
    private const F = 1 / 298.257222101;
    private const K0 = 0.9996;
    private const FALSE_EASTING = 500000.0;
    private const LON0 = 9.0;                 // Mittelmeridian Zone 32

    /** @var array{0: float, 1: float[], 2: float[]}|null */
    private static ?array $koeff = null;

    /** @return array{0: float, 1: float} [lat, lon] in Grad */
    public static function toLatLon(float $easting, float $northing): array
    {
        [$bigA, $beta, $delta] = self::koeffizienten();

        $xi = $northing / (self::K0 * $bigA);
        $eta = ($easting - self::FALSE_EASTING) / (self::K0 * $bigA);

        $xiS = $xi;
        $etaS = $eta;
        for ($j = 1; $j <= 4; $j++) {
            $xiS -= $beta[$j] * sin(2 * $j * $xi) * cosh(2 * $j * $eta);
            $etaS -= $beta[$j] * cos(2 * $j * $xi) * sinh(2 * $j * $eta);
        }

        $chi = asin(sin($xiS) / cosh($etaS));
        $phi = $chi;
        for ($j = 1; $j <= 4; $j++) {
            $phi += $delta[$j] * sin(2 * $j * $chi);
        }
        $lambda = deg2rad(self::LON0) + atan2(sinh($etaS), cos($xiS));

        return [rad2deg($phi), rad2deg($lambda)];
    }

    /** @return array{0: float, 1: float[], 2: float[]} */
    private static function koeffizienten(): array
    {
        if (self::$koeff !== null) {
            return self::$koeff;
        }
        $n = self::F / (2 - self::F);
        $n2 = $n * $n;
        $n3 = $n2 * $n;
        $n4 = $n3 * $n;
        $bigA = self::A / (1 + $n) * (1 + $n2 / 4 + $n4 / 64);
        $beta = [
            1 => $n / 2 - 2 / 3 * $n2 + 37 / 96 * $n3 - 1 / 360 * $n4,
            2 => 1 / 48 * $n2 + 1 / 15 * $n3 - 437 / 1440 * $n4,
            3 => 17 / 480 * $n3 - 37 / 840 * $n4,
            4 => 4397 / 161280 * $n4,
        ];
        $delta = [
            1 => 2 * $n - 2 / 3 * $n2 - 2 * $n3 + 116 / 45 * $n4,
            2 => 7 / 3 * $n2 - 8 / 5 * $n3 - 227 / 45 * $n4,
            3 => 56 / 15 * $n3 - 136 / 35 * $n4,
            4 => 4279 / 630 * $n4,
        ];
        return self::$koeff = [$bigA, $beta, $delta];
    }
}
