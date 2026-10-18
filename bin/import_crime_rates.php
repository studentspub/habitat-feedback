<?php
/**
 * Einmaliger (jährlich wiederholbarer) Import der Kriminalitätsrate je
 * Kreis aus der Polizeilichen Kriminalstatistik (PKS) des BKA in
 * crime_rates. Bulk-import-once wie bei climate_normals/air_stations/
 * unemployment_rates — die PKS erscheint ohnehin nur einmal jährlich.
 *
 * Quelle ist eine öffentliche, unauthentifizierte Excel-Tabelle des BKA
 * (Tabelle 01 "Kreise - Fälle - HZ", Fälle + Häufigkeitszahl je Kreis),
 * kein API-Zugang nötig.
 *
 * Aufruf: php bin/import_crime_rates.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';
require __DIR__ . '/../app/Support/SimpleXlsxReader.php';

use App\Support\Db;
use App\Support\KreisNames;
use App\Support\SimpleXlsxReader;

// Feste URL fürs aktuelle Berichtsjahr — das BKA veröffentlicht jedes Jahr
// eine neue Tabelle unter dem entsprechenden Jahres-Pfad; beim nächsten
// jährlichen Re-Import muss diese Konstante auf das neue Jahr angepasst
// werden (kein automatisch auflösbares "aktuellstes Jahr" gefunden).
const PKS_YEAR = 2025;
const PKS_URL = 'https://www.bka.de/SharedDocs/Downloads/DE/Publikationen/PolizeilicheKriminalstatistik/'
    . PKS_YEAR . '/Kreis/Faelle/KR-F-01-T01-Kreise-Faelle-HZ_xls.xlsx?__blob=publicationFile&v=2';
const SHEET_NAME = 'T01_Kreise';
const TOTAL_CRIME_LABEL = 'Straftaten insgesamt';
const KREISFREI_ARTEN = ['KfS', 'SK']; // kreisfreie Stadt / Stadtkreis (BW) -> wie "Stadt" behandeln

function downloadXlsx(string $url, string $targetPath): void
{
    $ch = curl_init($url);
    $fh = fopen($targetPath, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['User-Agent: habitat-feedback-import/1.0 (+https://habitat-feedback.de; cihanaldanmaz@gmail.com)'],
        CURLOPT_TIMEOUT => 60,
    ]);
    $ok = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fh);

    if (!$ok || $httpCode !== 200) {
        unlink($targetPath);
        throw new RuntimeException("Download fehlgeschlagen (HTTP $httpCode).");
    }
}

echo 'Lade PKS-Tabelle ' . PKS_YEAR . " vom BKA...\n";
$tmpFile = sys_get_temp_dir() . '/habitat_feedback_pks_' . PKS_YEAR . '.xlsx';
downloadXlsx(PKS_URL, $tmpFile);
echo '  ' . round(filesize($tmpFile) / 1024) . " KB geladen.\n";

$rows = SimpleXlsxReader::readSheet($tmpFile, SHEET_NAME);
unlink($tmpFile);

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO crime_rates (kreis_name, cases, haeufigkeitszahl, reporting_year)
     VALUES (:kreis, :cases, :hz, :year)
     ON DUPLICATE KEY UPDATE cases = VALUES(cases), haeufigkeitszahl = VALUES(haeufigkeitszahl),
       reporting_year = VALUES(reporting_year), imported_at = NOW()'
);

// Spaltenreihenfolge laut Kopfzeile der Tabelle (siehe Kommentar in
// KreisNames.php-Nachbarschaft): 0=Schlüssel, 1=Straftat, 2=Gemeindeschlüssel,
// 3=Stadt-/Landkreis, 4=Kreisart, 5=Anzahl erfasste Fälle, 6=HZ, ...
$imported = 0;
$unmatched = [];
foreach ($rows as $row) {
    if (($row[1] ?? null) !== TOTAL_CRIME_LABEL) {
        continue; // nur die Gesamtzeile je Kreis, nicht die 40 Einzeldelikt-Zeilen
    }
    $name = $row[3] ?? null;
    $art = $row[4] ?? null;
    $cases = $row[5] ?? null;
    $hz = $row[6] ?? null;
    if ($name === null || $cases === null || $hz === null) {
        continue;
    }

    $isKreisfrei = in_array($art, KREISFREI_ARTEN, true);
    $kreisName = $isKreisfrei ? KreisNames::match(null, $name) : KreisNames::match($name, null);
    if ($kreisName === null) {
        $unmatched[] = "$name ($art)";
        continue;
    }

    $upsert->execute([
        'kreis' => $kreisName,
        'cases' => (int) $cases,
        'hz' => round((float) $hz, 1),
        'year' => PKS_YEAR,
    ]);
    $imported++;
}

echo "$imported Kreise importiert.\n";
if ($unmatched !== []) {
    fwrite(STDERR, 'Nicht zugeordnet: ' . implode(', ', $unmatched) . "\n");
}
