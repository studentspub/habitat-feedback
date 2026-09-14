<?php

namespace App\Support;

final class Fingerprint
{
    /**
     * Rohes Geräte-/Kontext-Signal zum Zeitpunkt der Einreichung — noch ohne
     * Bezug zu einem Abschnitt (der ist ja erst nach der Geokodierung bekannt).
     * Enthält keine Klarnamen, keine E-Mail, keine volle IP.
     */
    public static function rawSignal(): string
    {
        $ipPrefix = self::ipPrefix($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return $ipPrefix . '|' . $userAgent;
    }

    /**
     * Finaler, pro Abschnitt eindeutiger Hash — erst nach der Geokodierung
     * gebildet, damit sich ein Gerät über verschiedene Abschnitte hinweg
     * nicht korrelieren lässt (siehe Konzeptpapier Abschnitt 02).
     */
    public static function finalHash(int $segmentId, string $rawSignal): string
    {
        $config = require __DIR__ . '/../../config/config.php';
        return hash('sha256', $config['fingerprint_salt'] . '|' . $segmentId . '|' . $rawSignal);
    }

    /** Kappt die IP auf ein /24- bzw. /48-Präfix statt sie voll zu speichern. */
    private static function ipPrefix(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 3)) . '::/48';
        }
        $parts = explode('.', $ip);
        return implode('.', array_slice($parts, 0, 3)) . '.0/24';
    }
}
