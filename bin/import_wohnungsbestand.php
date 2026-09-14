<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import des Wohnungsbestands je Kreis
 * aus der Regionaldatenbank Deutschland (GENESIS, Statistik 31231, Tabelle
 * 31231-02-01-4) in wohnungsbestand.
 *
 * Zeilenformat (datencsv): Stichtag(31.12.JJJJ);AGS;Kreisname;Wohngebäude
 * insgesamt;...;Wohnfläche;Wohnungen insgesamt(Index 9);...
 * Uns interessiert nur Index 9 ("Wohnungen in Wohn- und Nichtwohngebäuden,
 * Insgesamt").
 *
 * Aufruf: php bin/import_wohnungsbestand.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/GenesisClient.php';

use App\Support\Db;
use App\Support\GenesisClient;
use App\Support\KreisNames;

const TABLE_NAME = '31231-02-01-4';
const START_YEAR = 2023; // wir wollen nur das aktuellste verfügbare Jahr

$config = require __DIR__ . '/../config/config.php';
$genesis = new GenesisClient($config['genesis_api_token']);

echo "Lade Wohnungsbestand-Tabelle von der Regionaldatenbank Deutschland...\n";
$rows = $genesis->fetchTableAllKreise(TABLE_NAME, START_YEAR);
echo '  ' . count($rows) . " Zeilen geladen.\n";

// Stichtag statt Jahr in Spalte 0 (Format "31.12.JJJJ") — das jüngste Jahr
// bestimmen, indem wir den Teil nach dem letzten Punkt vergleichen.
$yearOf = static fn (string $stichtag): int => (int) substr($stichtag, -4);
$latestYear = max(array_map(static fn (array $r): int => $yearOf($r[0]), $rows));

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO wohnungsbestand (kreis_name, anzahl_wohnungen, reporting_year)
     VALUES (:kreis, :anzahl, :year)
     ON DUPLICATE KEY UPDATE anzahl_wohnungen = VALUES(anzahl_wohnungen),
       reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

$imported = 0;
$unmatched = [];
foreach ($rows as $row) {
    if ($yearOf($row[0]) !== $latestYear) {
        continue;
    }
    $genesisName = $row[2] ?? null;
    $wohnungenInsgesamt = $row[9] ?? null;
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

echo "$imported Kreise importiert (Stichtag 31.12.$latestYear).\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
