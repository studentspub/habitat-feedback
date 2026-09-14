<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import des verfügbaren Einkommens der
 * privaten Haushalte je Einwohner und Kreis aus der Regionaldatenbank
 * Deutschland (GENESIS, Statistik 82000, Tabelle 82000-07-01-4) in
 * einkommen_haushalte.
 *
 * Zeilenformat (datencsv): Jahr;AGS;Kreisname;Einkommen gesamt (Tsd. EUR);
 * Einkommen je Einwohner (EUR) — uns interessiert nur Index 4.
 *
 * Aufruf: php bin/import_einkommen.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/GenesisClient.php';

use App\Support\Db;
use App\Support\GenesisClient;
use App\Support\KreisNames;

const TABLE_NAME = '82000-07-01-4';
const START_YEAR = 2022;

$config = require __DIR__ . '/../config/config.php';
$genesis = new GenesisClient($config['genesis_api_token']);

echo "Lade Einkommens-Tabelle von der Regionaldatenbank Deutschland...\n";
$rows = $genesis->fetchTableAllKreise(TABLE_NAME, START_YEAR);
echo '  ' . count($rows) . " Zeilen geladen.\n";

$latestYear = max(array_map(static fn (array $r): int => (int) $r[0], $rows));

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO einkommen_haushalte (kreis_name, je_einwohner_eur, reporting_year)
     VALUES (:kreis, :betrag, :year)
     ON DUPLICATE KEY UPDATE je_einwohner_eur = VALUES(je_einwohner_eur),
       reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

$imported = 0;
$unmatched = [];
foreach ($rows as $row) {
    if ((int) $row[0] !== $latestYear) {
        continue;
    }
    $genesisName = $row[2] ?? null;
    $jeEinwohner = $row[4] ?? null;
    if ($genesisName === null || $jeEinwohner === null || $jeEinwohner === '') {
        continue;
    }

    $kreisName = KreisNames::matchByGenesisName($genesisName);
    if ($kreisName === null) {
        $unmatched[] = $genesisName;
        continue;
    }

    $upsert->execute([
        'kreis' => $kreisName,
        'betrag' => (int) $jeEinwohner,
        'year' => $latestYear,
    ]);
    $imported++;
}

echo "$imported Kreise importiert (Berichtsjahr $latestYear).\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
