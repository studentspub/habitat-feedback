<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import der Empfänger von
 * Grundsicherung im Alter und bei Erwerbsminderung je Kreis aus der
 * Regionaldatenbank Deutschland (GENESIS, Statistik 22151, Tabelle
 * 22151-01-02-4) in grundsicherung_alter.
 *
 * Zeilenformat (datencsv, "Insgesamt"-Zeile über beide Geschlechter):
 * Jahr;AGS;Kreisname;Geschlecht;Insgesamt;18-Altersgrenze;Altersgrenze+;...
 * Uns interessiert nur die Spalte "Altersgrenze und älter" (Index 6) der
 * "Insgesamt"-Zeile (Index 3 = "Insgesamt", nicht "männlich"/"weiblich") —
 * das ist die eigentliche Grundsicherung-im-Alter-Zahl (Rentner), getrennt
 * von den unter der Regelaltersgrenze liegenden Erwerbsminderungs-Fällen.
 *
 * Aufruf: php bin/import_grundsicherung.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/GenesisClient.php';

use App\Support\Db;
use App\Support\GenesisClient;
use App\Support\KreisNames;

const TABLE_NAME = '22151-01-02-4';
const START_YEAR = 2024; // wir wollen nur das aktuellste verfügbare Jahr

$config = require __DIR__ . '/../config/config.php';
$genesis = new GenesisClient($config['genesis_api_token']);

echo "Lade Grundsicherung-im-Alter-Tabelle von der Regionaldatenbank Deutschland...\n";
$rows = $genesis->fetchTableAllKreise(TABLE_NAME, START_YEAR);
echo '  ' . count($rows) . " Zeilen geladen.\n";

// Nur das jüngste gelieferte Jahr behalten (die Tabelle liefert mehrere
// Jahre zurück, weil startyear nur eine Untergrenze ist).
$latestYear = max(array_map(static fn (array $r): int => (int) $r[0], $rows));

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO grundsicherung_alter (kreis_name, empfaenger_altersgrenze, reporting_year)
     VALUES (:kreis, :anzahl, :year)
     ON DUPLICATE KEY UPDATE empfaenger_altersgrenze = VALUES(empfaenger_altersgrenze),
       reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

$imported = 0;
$unmatched = [];
foreach ($rows as $row) {
    $year = (int) $row[0];
    $genesisName = $row[2] ?? null;
    $geschlecht = $row[3] ?? null;
    $altersgrenzeUndAelter = $row[6] ?? null;

    if ($year !== $latestYear || $geschlecht !== 'Insgesamt' || $genesisName === null || $altersgrenzeUndAelter === null) {
        continue;
    }

    $kreisName = KreisNames::matchByGenesisName($genesisName);
    if ($kreisName === null) {
        $unmatched[] = $genesisName;
        continue;
    }

    $upsert->execute([
        'kreis' => $kreisName,
        'anzahl' => (int) $altersgrenzeUndAelter,
        'year' => $year,
    ]);
    $imported++;
}

echo "$imported Kreise importiert (Berichtsjahr $latestYear).\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
