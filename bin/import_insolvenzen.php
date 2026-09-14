<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import der beantragten
 * Insolvenzverfahren (insgesamt, Unternehmen + Privatpersonen) je Kreis aus
 * der Regionaldatenbank Deutschland (GENESIS, Statistik 52411, Tabelle
 * 52411-01-01-4) in insolvenzen.
 *
 * Zeilenformat (datencsv): Jahr;AGS;Kreisname;Insgesamt (Index 3);...
 *
 * Aufruf: php bin/import_insolvenzen.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/GenesisClient.php';

use App\Support\Db;
use App\Support\GenesisClient;
use App\Support\KreisNames;

const TABLE_NAME = '52411-01-01-4';
const START_YEAR = 2023;

$config = require __DIR__ . '/../config/config.php';
$genesis = new GenesisClient($config['genesis_api_token']);

echo "Lade Insolvenzen-Tabelle von der Regionaldatenbank Deutschland...\n";
$rows = $genesis->fetchTableAllKreise(TABLE_NAME, START_YEAR);
echo '  ' . count($rows) . " Zeilen geladen.\n";

$latestYear = max(array_map(static fn (array $r): int => (int) $r[0], $rows));

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO insolvenzen (kreis_name, anzahl, reporting_year)
     VALUES (:kreis, :anzahl, :year)
     ON DUPLICATE KEY UPDATE anzahl = VALUES(anzahl),
       reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

$imported = 0;
$unmatched = [];
foreach ($rows as $row) {
    if ((int) $row[0] !== $latestYear) {
        continue;
    }
    $genesisName = $row[2] ?? null;
    $insgesamt = $row[3] ?? null;
    if ($genesisName === null || $insgesamt === null) {
        continue;
    }

    $kreisName = KreisNames::matchByGenesisName($genesisName);
    if ($kreisName === null) {
        $unmatched[] = $genesisName;
        continue;
    }

    $upsert->execute([
        'kreis' => $kreisName,
        'anzahl' => (int) $insgesamt,
        'year' => $latestYear,
    ]);
    $imported++;
}

echo "$imported Kreise importiert (Berichtsjahr $latestYear).\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
