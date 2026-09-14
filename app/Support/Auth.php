<?php

namespace App\Support;

final class Auth
{
    /** Prüft den Bearer-Token der Pi-Endpunkte gegen config.php, timing-safe. */
    public static function checkPiToken(): bool
    {
        $config = require __DIR__ . '/../../config/config.php';
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }
        $given = substr($header, strlen('Bearer '));
        return hash_equals($config['pi_api_token'], $given);
    }
}
