<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import der Gewerbean-/-abmeldungen je
 * Kreis aus der Regionaldatenbank Deutschland (GENESIS, Statistik 52311,
 * Tabelle 52311-01-04-4) in gewerbeanzeigen.
 *
 * Zeilenformat (datencsv): Jahr;AGS;Kreisname;Anmeldungen insgesamt (Index
 * 3);...;Abmeldungen insgesamt (Index 8);...
 *
 * Aufruf: php bin/import_gewerbeanzeigen.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/GenesisClient.php';

use App\Support\Db;
use App\Support\GenesisClient;
use App\Support\KreisNames;

const TABLE_NAME = '52311-01-04-4';
const START_YEAR = 2024;

$config = require __DIR__ . '/../config/config.php';
$genesis = new GenesisClient($config['genesis_api_token']);

echo "Lade Gewerbeanzeigen-Tabelle von der Regionaldatenbank Deutschland...\n";
$rows = $genesis->fetchTableAllKreise(TABLE_NAME, START_YEAR);
echo '  ' . count($rows) . " Zeilen geladen.\n";

$latestYear = max(array_map(static fn (array $r): int => (int) $r[0], $rows));

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO gewerbeanzeigen (kreis_name, anmeldungen, abmeldungen, reporting_year)
     VALUES (:kreis, :anmeldungen, :abmeldungen, :year)
     ON DUPLICATE KEY UPDATE anmeldungen = VALUES(anmeldungen), abmeldungen = VALUES(abmeldungen),
       reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

$imported = 0;
$unmatched = [];
foreach ($rows as $row) {
    if ((int) $row[0] !== $latestYear) {
        continue;
    }
    $genesisName = $row[2] ?? null;
    $anmeldungen = $row[3] ?? null;
    $abmeldungen = $row[8] ?? null;
    if ($genesisName === null || $anmeldungen === null || $abmeldungen === null) {
        continue;
    }

    $kreisName = KreisNames::matchByGenesisName($genesisName);
    if ($kreisName === null) {
        $unmatched[] = $genesisName;
        continue;
    }

    $upsert->execute([
        'kreis' => $kreisName,
        'anmeldungen' => (int) $anmeldungen,
        'abmeldungen' => (int) $abmeldungen,
        'year' => $latestYear,
    ]);
    $imported++;
}

echo "$imported Kreise importiert (Berichtsjahr $latestYear).\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
