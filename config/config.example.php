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

    // Sichtbarkeitsschwellen — siehe Konzeptpapier Abschnitt 02.
    'visibility' => [
        'min_address_count' => 6,
        'min_rating_count'  => 3,
    ],
];
