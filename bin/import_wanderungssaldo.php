<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import des Wanderungssaldos je Kreis
 * aus der Regionaldatenbank Deutschland (GENESIS, Statistik 12711, Tabelle
 * 12711-91-01-4) in wanderungssaldo.
 *
 * Zeilenformat (datencsv): Jahr;AGS;Kreisname;Zuzüge;Fortzüge (Index 3, 4).
 *
 * Aufruf: php bin/import_wanderungssaldo.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/GenesisClient.php';

use App\Support\Db;
use App\Support\GenesisClient;
use App\Support\KreisNames;

const TABLE_NAME = '12711-91-01-4';
const START_YEAR = 2023;

$config = require __DIR__ . '/../config/config.php';
$genesis = new GenesisClient($config['genesis_api_token']);

echo "Lade Wanderungsstatistik-Tabelle von der Regionaldatenbank Deutschland...\n";
$rows = $genesis->fetchTableAllKreise(TABLE_NAME, START_YEAR);
echo '  ' . count($rows) . " Zeilen geladen.\n";

$latestYear = max(array_map(static fn (array $r): int => (int) $r[0], $rows));

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO wanderungssaldo (kreis_name, zuzuege, fortzuege, saldo, reporting_year)
     VALUES (:kreis, :zuzuege, :fortzuege, :saldo, :year)
     ON DUPLICATE KEY UPDATE zuzuege = VALUES(zuzuege), fortzuege = VALUES(fortzuege),
       saldo = VALUES(saldo), reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

$imported = 0;
$unmatched = [];
foreach ($rows as $row) {
    if ((int) $row[0] !== $latestYear) {
        continue;
    }
    $genesisName = $row[2] ?? null;
    $zuzuege = $row[3] ?? null;
    $fortzuege = $row[4] ?? null;
    if ($genesisName === null || $zuzuege === null || $fortzuege === null) {
        continue;
    }

    $kreisName = KreisNames::matchByGenesisName($genesisName);
    if ($kreisName === null) {
        $unmatched[] = $genesisName;
        continue;
    }

    $upsert->execute([
        'kreis' => $kreisName,
        'zuzuege' => (int) $zuzuege,
        'fortzuege' => (int) $fortzuege,
        'saldo' => (int) $zuzuege - (int) $fortzuege,
        'year' => $latestYear,
    ]);
    $imported++;
}

echo "$imported Kreise importiert (Berichtsjahr $latestYear).\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
