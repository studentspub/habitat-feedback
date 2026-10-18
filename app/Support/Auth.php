<?php

namespace App\Support;

final class Auth
{
    /** Prüft den Bearer-Token der Pi-Endpunkte gegen config.php, timing-safe. */
    public static function checkPiToken(): bool
    {
        $config = require __DIR__ . '/../../config/config.php';
        // REDIRECT_HTTP_AUTHORIZATION: so kommt der Header an, wenn ihn die .htaccess per Rewrite-Umgebungsvariable
        // an PHP-FastCGI durchreicht (Strato, Issue #39); direkt gesetzt bei mod_php und php -S.
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }
        $given = substr($header, strlen('Bearer '));
        return hash_equals($config['pi_api_token'], $given);
    }
}
