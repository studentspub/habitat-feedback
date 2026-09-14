<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import der Baufertigstellungen (neue
 * Wohnungen) je Kreis aus der Regionaldatenbank Deutschland (GENESIS,
 * Statistik 31121, Tabelle 31121-01-02-4) in baufertigstellungen.
 *
 * Identisches Spaltenformat wie Baugenehmigungen (siehe
 * bin/import_baugenehmigungen.php) — Wohnungen insgesamt in Index 7.
 *
 * Aufruf: php bin/import_baufertigstellungen.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/GenesisClient.php';

use App\Support\Db;
use App\Support\GenesisClient;
use App\Support\KreisNames;

const TABLE_NAME = '31121-01-02-4';
const START_YEAR = 2023;

$config = require __DIR__ . '/../config/config.php';
$genesis = new GenesisClient($config['genesis_api_token']);

echo "Lade Baufertigstellungen-Tabelle von der Regionaldatenbank Deutschland...\n";
$rows = $genesis->fetchTableAllKreise(TABLE_NAME, START_YEAR);
echo '  ' . count($rows) . " Zeilen geladen.\n";

$latestYear = max(array_map(static fn (array $r): int => (int) $r[0], $rows));

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO baufertigstellungen (kreis_name, anzahl_wohnungen, reporting_year)
     VALUES (:kreis, :anzahl, :year)
     ON DUPLICATE KEY UPDATE anzahl_wohnungen = VALUES(anzahl_wohnungen),
       reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

$imported = 0;
$unmatched = [];
foreach ($rows as $row) {
    if ((int) $row[0] !== $latestYear) {
        continue;
    }
    $genesisName = $row[2] ?? null;
    $wohnungenInsgesamt = $row[7] ?? null;
    if ($genesisName === null || $wohnungenInsgesamt === null) {
        continue;
    }

    $kreisName = KreisNames::matchByGenesisName($genesisName);
    if ($kreisName === null) {
        $unmatched[] = $genesisName;
        continue;
    }

    $upsert->execute([
        'kreis' => $kreisName,
        'anzahl' => (int) $wohnungenInsgesamt,
        'year' => $latestYear,
    ]);
    $imported++;
}

echo "$imported Kreise importiert (Berichtsjahr $latestYear).\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
