<?php

namespace App\Support;

final class Net
{
    /** Kappt eine IP auf ein /24- bzw. /48-Präfix statt sie voll zu verarbeiten. */
    public static function ipPrefix(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 3)) . '::/48';
        }
        $parts = explode('.', $ip);
        return implode('.', array_slice($parts, 0, 3)) . '.0/24';
    }
}
