<?php

namespace App\Support;

/**
 * ETRS89-LAEA Europe (EPSG:3035) <-> geographische Koordinaten (ETRS89 ≈ WGS84).
 * Das Zensus-2022-Gitter (Issue #23) ist in diesem flächentreuen Raster definiert.
 * Formeln: Snyder, „Map Projections — A Working Manual“, Lambert Azimuthal
 * Equal-Area auf dem Ellipsoid (GRS80), Ursprung 52° N / 10° O,
 * Rechts-/Hochwert-Offset 4.321.000 / 3.210.000 m.
 */
final class Laea
{
    private const A = 6378137.0;
    private const F = 1 / 298.257222101;
    private const LAT0 = 52.0;
    private const LON0 = 10.0;
    private const FE = 4321000.0;
    private const FN = 3210000.0;

    /** @var array{e: float, e2: float, qp: float, sinB1: float, cosB1: float, rq: float, d: float}|null */
    private static ?array $k = null;

    /** @return array{0: float, 1: float} [x (Rechtswert), y (Hochwert)] in Metern */
    public static function fromLatLon(float $lat, float $lon): array
    {
        $k = self::konstanten();
        $beta = asin(self::q(deg2rad($lat)) / $k['qp']);
        $dl = deg2rad($lon - self::LON0);
        $b = $k['rq'] * sqrt(2 / (1 + $k['sinB1'] * sin($beta) + $k['cosB1'] * cos($beta) * cos($dl)));
        return [
            self::FE + $b * $k['d'] * cos($beta) * sin($dl),
            self::FN + ($b / $k['d']) * ($k['cosB1'] * sin($beta) - $k['sinB1'] * cos($beta) * cos($dl)),
        ];
    }

    /** @return array{0: float, 1: float} [lat, lon] in Grad */
    public static function toLatLon(float $x, float $y): array
    {
        $k = self::konstanten();
        $dx = $x - self::FE;
        $dy = $y - self::FN;
        $rho = sqrt(($dx / $k['d']) ** 2 + ($k['d'] * $dy) ** 2);
        if ($rho < 1e-9) {
            return [self::LAT0, self::LON0];
        }
        $c = 2 * asin($rho / (2 * $k['rq']));
        $beta = asin(cos($c) * $k['sinB1'] + ($k['d'] * $dy * sin($c) * $k['cosB1']) / $rho);
        $lam = deg2rad(self::LON0) + atan2($dx * sin($c), $k['d'] * $rho * $k['cosB1'] * cos($c) - $k['d'] ** 2 * $dy * $k['sinB1'] * sin($c));
        $e2 = $k['e2'];
        $e4 = $e2 * $e2;
        $e6 = $e4 * $e2;
        $phi = $beta
            + ($e2 / 3 + 31 * $e4 / 180 + 517 * $e6 / 5040) * sin(2 * $beta)
            + (23 * $e4 / 360 + 251 * $e6 / 3780) * sin(4 * $beta)
            + (761 * $e6 / 45360) * sin(6 * $beta);
        return [rad2deg($phi), rad2deg($lam)];
    }

    private static function q(float $phi): float
    {
        $k = self::$k ?? self::konstanten();
        $e = $k['e'];
        $s = sin($phi);
        return (1 - $k['e2']) * ($s / (1 - $k['e2'] * $s * $s) - (1 / (2 * $e)) * log((1 - $e * $s) / (1 + $e * $s)));
    }

    private static function konstanten(): array
    {
        if (self::$k !== null) {
            return self::$k;
        }
        $e2 = self::F * (2 - self::F);
        $e = sqrt($e2);
        self::$k = ['e' => $e, 'e2' => $e2, 'qp' => 0, 'sinB1' => 0, 'cosB1' => 0, 'rq' => 0, 'd' => 0];
        $qp = self::q(M_PI / 2);
        $phi1 = deg2rad(self::LAT0);
        $beta1 = asin(self::q($phi1) / $qp);
        $rq = self::A * sqrt($qp / 2);
        $m1 = cos($phi1) / sqrt(1 - $e2 * sin($phi1) ** 2);
        self::$k = [
            'e' => $e, 'e2' => $e2, 'qp' => $qp, 'sinB1' => sin($beta1), 'cosB1' => cos($beta1), 'rq' => $rq,
            'd' => self::A * $m1 / ($rq * cos($beta1)),
        ];
        return self::$k;
    }
}
