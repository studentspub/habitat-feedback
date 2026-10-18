<?php
/**
 * Einmaliger (periodisch wiederholbarer) Import der Arbeitslosenquote für
 * alle 401 Kreise/kreisfreien Städte in unemployment_rates — von der API
 * der Statistik der Bundesagentur für Arbeit (seit 12/2025 öffentlich,
 * siehe app/Support/KreisNames.php für Details zur Namensliste).
 *
 * Läuft bewusst lange: pro Kreis eine eigene Anfrage (~20-25s Antwortzeit
 * gemessen, offenbar serverseitig live berechnet statt aus einem Cache
 * bedient), macht bei 401 Kreisen mehrere Stunden Gesamtlaufzeit. Genau wie
 * bei bin/import_poi.php also ein Hintergrund-Skript, kein Live-Request.
 *
 * Aufruf: php bin/import_unemployment.php
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/KreisNames.php';

use App\Support\Db;
use App\Support\KreisNames;

const MAX_ATTEMPTS = 3;
const REQUEST_TIMEOUT_SECONDS = 45;
const PAUSE_BETWEEN_REQUESTS_SECONDS = 1;

/**
 * @return array{quote: float, month: string}|null
 */
function fetchQuoteForKreis(string $kreisName): ?array
{
    $url = 'https://statistik-dr.arbeitsagentur.de/bifrontend/bids-api/ct/v1/tableFetch/csv/EckwerteTabelleALOKr?'
        . http_build_query(['Kreis' => $kreisName]);

    for ($attempt = 1; $attempt <= MAX_ATTEMPTS; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['User-Agent: habitat-feedback-import/1.0 (+https://habitat-feedback.de; cihanaldanmaz@gmail.com)'],
            CURLOPT_TIMEOUT => REQUEST_TIMEOUT_SECONDS,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $parsed = parseCsv($response);
            if ($parsed !== null) {
                return $parsed;
            }
            fwrite(STDERR, "  Antwort für '$kreisName' konnte nicht geparst werden (Format geändert?).\n");
            return null;
        }

        fwrite(STDERR, "  Versuch $attempt/" . MAX_ATTEMPTS . " für '$kreisName' fehlgeschlagen (HTTP $httpCode).\n");
        if ($attempt < MAX_ATTEMPTS) {
            sleep(5 * $attempt);
        }
    }

    return null;
}

/**
 * Die CSV ist Semikolon-getrennt, mit deutschem Dezimalkomma und einer
 * UTF-8-BOM am Anfang. Die gesuchte Zeile beginnt mit dem exakten Label
 * "Arbeitslosenquote (bezogen auf alle zivilen Erwerbspersonen)" — bewusst
 * nicht die Zeilen "im Rechtskreis SGB III/II" darunter, die sind
 * Teilquoten, nicht die Gesamtquote.
 */
function parseCsv(string $csv): ?array
{
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
    $lines = explode("\n", $csv);

    $month = null;
    $quote = null;
    foreach ($lines as $line) {
        $cols = array_map('trim', explode(';', $line));
        if ($month === null && $cols[0] === 'Berichtsmonat:') {
            $month = $cols[1] ?? null;
        }
        if ($cols[0] === 'Arbeitslosenquote (bezogen auf alle zivilen Erwerbspersonen)') {
            $raw = $cols[1] ?? '';
            if ($raw === '' || $raw === '-') {
                return null; // statistisch gesperrt (zu kleiner Kreis) oder kein Wert
            }
            $quote = (float) str_replace(',', '.', $raw);
        }
    }

    if ($month === null || $quote === null) {
        return null;
    }
    return ['quote' => $quote, 'month' => $month];
}

$db = Db::get();
$upsert = $db->prepare(
    'INSERT INTO unemployment_rates (kreis_name, quote, reporting_month)
     VALUES (:kreis, :quote, :month)
     ON DUPLICATE KEY UPDATE quote = VALUES(quote), reporting_month = VALUES(reporting_month), imported_at = NOW()'
);
$alreadyDone = $db->query(
    "SELECT kreis_name FROM unemployment_rates WHERE imported_at >= NOW() - INTERVAL 12 HOUR"
)->fetchAll(\PDO::FETCH_COLUMN);
$alreadyDone = array_flip($alreadyDone);

$total = count(KreisNames::all());
$done = 0;
$failed = 0;
foreach (KreisNames::all() as $kreisName) {
    $done++;
    // Bei > 400 Anfragen à ~20s ist eine Unterbrechung (Verbindungsabbruch,
    // Server-Neustart) auf mehrstündiger Laufzeit eher die Regel als die
    // Ausnahme — ein Neustart des Skripts soll dann nicht wieder von vorne
    // anfangen. "vor < 12h importiert" gilt als erledigt.
    if (isset($alreadyDone[$kreisName])) {
        echo "[$done/$total] $kreisName ... bereits aktuell, übersprungen.\n";
        continue;
    }
    echo "[$done/$total] $kreisName ... ";
    $result = fetchQuoteForKreis($kreisName);
    if ($result === null) {
        echo "übersprungen.\n";
        $failed++;
    } else {
        $upsert->execute(['kreis' => $kreisName, 'quote' => $result['quote'], 'month' => $result['month']]);
        echo $result['quote'] . "% (" . $result['month'] . ")\n";
    }
    sleep(PAUSE_BETWEEN_REQUESTS_SECONDS);
}

echo "Fertig. $done Kreise verarbeitet, $failed übersprungen.\n";
