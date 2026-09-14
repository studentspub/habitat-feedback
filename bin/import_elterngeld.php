<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import der Elterngeld-Bezieher je
 * Kreis aus der Regionaldatenbank Deutschland (GENESIS, Statistik 22922,
 * Tabelle 22922-01-01-4) in elterngeld_bezieher.
 *
 * Zeilenformat (datencsv, "Insgesamt"-Spalte über beide Geschlechter):
 * Jahr;AGS;Kreisname;Quartal;Insgesamt;Männer;Frauen;...
 * Die Quelle liefert quartalsweise Werte (nicht jährlich wie die anderen
 * Statistiken) — wir summieren die vier jüngsten verfügbaren Quartale je
 * Kreis zu einer Jahreszahl, damit sich das Ergebnis wie die übrigen
 * Wirtschafts-Kennzahlen als ein Wert pro Kreis anzeigen lässt.
 *
 * Aufruf: php bin/import_elterngeld.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/GenesisClient.php';

use App\Support\Db;
use App\Support\GenesisClient;
use App\Support\KreisNames;

const TABLE_NAME = '22922-01-01-4';
const START_YEAR = 2024; // wir wollen nur die letzten paar Quartale

function quarterIndex(int $year, string $quartalLabel): ?int
{
    if (!preg_match('/^(\d)\./', $quartalLabel, $m)) {
        return null;
    }
    return $year * 4 + ((int) $m[1] - 1);
}

$config = require __DIR__ . '/../config/config.php';
$genesis = new GenesisClient($config['genesis_api_token']);

echo "Lade Elterngeld-Tabelle von der Regionaldatenbank Deutschland...\n";
$rows = $genesis->fetchTableAllKreise(TABLE_NAME, START_YEAR);
echo '  ' . count($rows) . " Zeilen geladen.\n";

$maxQuarterIndex = 0;
foreach ($rows as $row) {
    $qi = quarterIndex((int) $row[0], $row[3] ?? '');
    if ($qi !== null) {
        $maxQuarterIndex = max($maxQuarterIndex, $qi);
    }
}
$targetQuarters = range($maxQuarterIndex - 3, $maxQuarterIndex);
$latestYear = intdiv($maxQuarterIndex, 4);

$sums = []; // genesisName => Summe
foreach ($rows as $row) {
    $qi = quarterIndex((int) $row[0], $row[3] ?? '');
    $genesisName = $row[2] ?? null;
    $insgesamt = $row[4] ?? null;
    if ($qi === null || !in_array($qi, $targetQuarters, true) || $genesisName === null || $insgesamt === null) {
        continue;
    }
    $sums[$genesisName] = ($sums[$genesisName] ?? 0) + (int) $insgesamt;
}

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO elterngeld_bezieher (kreis_name, bezieher_anzahl, reporting_year)
     VALUES (:kreis, :anzahl, :year)
     ON DUPLICATE KEY UPDATE bezieher_anzahl = VALUES(bezieher_anzahl),
       reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

$imported = 0;
$unmatched = [];
foreach ($sums as $genesisName => $anzahl) {
    $kreisName = KreisNames::matchByGenesisName($genesisName);
    if ($kreisName === null) {
        $unmatched[] = $genesisName;
        continue;
    }
    $upsert->execute(['kreis' => $kreisName, 'anzahl' => $anzahl, 'year' => $latestYear]);
    $imported++;
}

echo "$imported Kreise importiert (4 Quartale bis Berichtsjahr $latestYear).\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
