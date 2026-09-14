<?php

namespace App\Support;

final class RateLimit
{
    /**
     * true, wenn das aktuelle IP-Präfix für diesen Endpunkt das Limit
     * bereits erreicht hat. Zählt bei false den aktuellen Versuch gleich mit.
     */
    public static function tooMany(string $endpoint): bool
    {
        $config = require __DIR__ . '/../../config/config.php';
        $rule = $config['rate_limits'][$endpoint] ?? null;
        if ($rule === null) {
            return false;
        }

        $db = Db::get();
        $ipPrefix = Net::ipPrefix($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        // Alte Einträge aufräumen — billig genug, um es einfach bei jedem
        // Check mitzumachen, kein eigener Cronjob nötig.
        $db->prepare('DELETE FROM rate_limit_hits WHERE created_at < :cutoff')
            ->execute(['cutoff' => date('Y-m-d H:i:s', time() - 86400)]);

        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits
             WHERE ip_prefix = :ip AND endpoint = :endpoint AND created_at >= :since'
        );
        $countStmt->execute([
            'ip' => $ipPrefix,
            'endpoint' => $endpoint,
            'since' => date('Y-m-d H:i:s', time() - $rule['window_seconds']),
        ]);

        if ((int) $countStmt->fetchColumn() >= $rule['limit']) {
            return true;
        }

        $db->prepare('INSERT INTO rate_limit_hits (ip_prefix, endpoint) VALUES (:ip, :endpoint)')
            ->execute(['ip' => $ipPrefix, 'endpoint' => $endpoint]);

        return false;
    }
}
