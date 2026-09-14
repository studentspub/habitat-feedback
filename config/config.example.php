<?php
// Vorlage — nach config.php kopieren und mit echten Werten füllen.
// config.php selbst ist per .gitignore ausgeschlossen und landet nie im Repo.

return [
    'db' => [
        'host'    => '127.0.0.1',
        'dbname'  => 'habitat_feedback_dev',
        'user'    => 'habitat-feedback',
        'pass'    => 'changeme',
        'charset' => 'utf8mb4',
    ],
    'pi_api_token' => 'changeme',

    // Persönlicher API-Token der Regionaldatenbank Deutschland (GENESIS),
    // sichtbar nach Login auf regionalstatistik.de im Modal
    // "Webservice-Schnittstelle (API)". Ersetzt Nutzername+Passwort.
    'genesis_api_token' => 'changeme',

    // Zufälliger, geheimer String — geht in den Fingerprint-Hash ein
    // (siehe Konzeptpapier Abschnitt 02, Abwehr von Mehrfachbewertungen).
    'fingerprint_salt' => 'changeme',

    // Sichtbarkeitsschwellen — siehe Konzeptpapier Abschnitt 02.
    'visibility' => [
        'min_address_count' => 6,
        'min_rating_count'  => 3,
    ],

    // Rate-Limiting pro IP-Präfix auf öffentlichen Schreib-Endpunkten
    // (Bauplan, Sicherheits-Checkliste).
    'rate_limits' => [
        'ratings' => ['limit' => 5, 'window_seconds' => 3600],
        'reports' => ['limit' => 10, 'window_seconds' => 3600],
    ],
];
