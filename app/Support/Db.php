<?php

namespace App\Support;

use PDO;

final class Db
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/config.php';
            $db = $config['db'];
            $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";
            self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // MariaDBs time_zone steht auf SYSTEM (WSL-Distro-Zeitzone,
            // z.B. UTC+2) — PHP läuft in UTC. Ohne das hier auf UTC zu
            // zwingen, liegen NOW()/CURRENT_TIMESTAMP in MySQL und PHPs
            // time() systematisch auseinander, was jeden Vergleich der
            // Art "vor weniger als X Sekunden" (Rate-Limiting, Nominatim-
            // Throttle) verlässlich falsch auswertet.
            self::$instance->exec("SET time_zone = '+00:00'");
        }

        return self::$instance;
    }
}
