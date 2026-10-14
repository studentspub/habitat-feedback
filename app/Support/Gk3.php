<?php

namespace App\Support;

/**
 * WGS84 (Grad) -> Gauß-Krüger Zone 3 (DHDN, EPSG:31467). Die 1-km-Klimaraster
 * des DWD liegen in GK3 (Issue #19).
 *
 * Zwei Schritte: (1) Datumswechsel WGS84 -> DHDN über geozentrische
 * Koordinaten mit der 3-Parameter-Verschiebung „DHDN to WGS 84 (1)“
 * (582 / 105 / 414 m, rückwärts) — Restfehler wenige Meter, ohne ihn läge der
 * Punkt bis ~150 m daneben. (2) Transversale Mercator-Projektion auf dem
 * Bessel-Ellipsoid, Krüger-Reihen 4. Ordnung (wie Utm32, nur vorwärts):
 * Mittelmeridian 9° O, Maßstab 1, Rechtswert-Offset 3.500.000 m.
 */
final class Gk3
{
    private const WGS_A = 6378137.0;
    private const WGS_F = 1 / 298.257223563;
    private const BESSEL_A = 6377397.155;
    private const BESSEL_F = 1 / 299.1528128;
    private const SHIFT = [582.0, 105.0, 414.0]; // DHDN -> WGS84, hier rückwärts angewandt
    private const LON0 = 9.0;
    private const FALSE_EASTING = 3500000.0;

    /** @return array{0: float, 1: float} [Rechtswert, Hochwert] in Metern */
    public static function fromLatLon(float $lat, float $lon): array
    {
        [$latB, $lonB] = self::wgs84ZuDhdn($lat, $lon);
        return self::projizieren($latB, $lonB);
    }

    /** @return array{0: float, 1: float} [lat, lon] auf DHDN/Bessel */
    private static function wgs84ZuDhdn(float $lat, float $lon): array
    {
        // geodätisch -> geozentrisch (WGS84, Höhe 0)
        $phi = deg2rad($lat);
        $lam = deg2rad($lon);
        $e2 = self::WGS_F * (2 - self::WGS_F);
        $n = self::WGS_A / sqrt(1 - $e2 * sin($phi) ** 2);
        $x = $n * cos($phi) * cos($lam) - self::SHIFT[0];
        $y = $n * cos($phi) * sin($lam) - self::SHIFT[1];
        $z = $n * (1 - $e2) * sin($phi) - self::SHIFT[2];

        // geozentrisch -> geodätisch (Bessel), iterativ
        $eb2 = self::BESSEL_F * (2 - self::BESSEL_F);
        $p = sqrt($x * $x + $y * $y);
        $phiB = atan2($z, $p * (1 - $eb2));
        for ($i = 0; $i < 5; $i++) {
            $nb = self::BESSEL_A / sqrt(1 - $eb2 * sin($phiB) ** 2);
            $h = $p / cos($phiB) - $nb;
            $phiB = atan2($z, $p * (1 - $eb2 * $nb / ($nb + $h)));
        }
        return [rad2deg($phiB), rad2deg(atan2($y, $x))];
    }

    /** @return array{0: float, 1: float} */
    private static function projizieren(float $lat, float $lon): array
    {
        $f = self::BESSEL_F;
        $n = $f / (2 - $f);
        $n2 = $n * $n;
        $n3 = $n2 * $n;
        $n4 = $n3 * $n;
        $bigA = self::BESSEL_A / (1 + $n) * (1 + $n2 / 4 + $n4 / 64);
        $alpha = [
            1 => $n / 2 - 2 / 3 * $n2 + 5 / 16 * $n3 + 41 / 180 * $n4,
            2 => 13 / 48 * $n2 - 3 / 5 * $n3 + 557 / 1440 * $n4,
            3 => 61 / 240 * $n3 - 103 / 140 * $n4,
            4 => 49561 / 161280 * $n4,
        ];
        $phi = deg2rad($lat);
        $dLam = deg2rad($lon - self::LON0);
        $k = 2 * sqrt($n) / (1 + $n);
        $t = sinh(atanh(sin($phi)) - $k * atanh($k * sin($phi)));
        $xi = atan($t / cos($dLam));
        $eta = atanh(sin($dLam) / sqrt(1 + $t * $t));

        $e = $eta;
        $nn = $xi;
        for ($j = 1; $j <= 4; $j++) {
            $e += $alpha[$j] * cos(2 * $j * $xi) * sinh(2 * $j * $eta);
            $nn += $alpha[$j] * sin(2 * $j * $xi) * cosh(2 * $j * $eta);
        }
        return [self::FALSE_EASTING + $bigA * $e, $bigA * $nn];
    }
}
