<?php
/**
 * Import ausgewählter BBSR-INKAR-Indikatoren (Issue #18) in inkar_werte und
 * inkar_indikatoren. Welche Indikatoren: app/Support/InkarIndikatoren.php.
 *
 * Die INKAR-Gesamtdatei ist eine einzige CSV mit 6,7 GB (alle ~600
 * Indikatoren, alle Raumebenen). Sie wird direkt aus dem Zip gestreamt
 * (`unzip -p` bzw. Python zipfile), Zeile für Zeile gefiltert und nie entpackt gespeichert.
 * Spalten: Bereich;ID;Kuerzel;Indikator;Raumbezug;Kennziffer;Name;Zeitbezug;Wert
 * (UTF-8, Dezimalkomma). Gemeinden und Kreise haben 8-stellige Kennziffern,
 * bei Kreisen mit angehängtem "000".
 *
 * Voraussetzung: Migration sql/020_inkar.sql; Download:
 * https://www.bbr-server.de/imagemap/inkar/download/inkar_2025.zip (434 MB)
 *
 * Die Zip nutzt Deflate64 — das entpackt nur `unzip` (nicht PHP, nicht Python).
 * Im Container gibt es kein unzip: dort mit dem Auszug aus WSL importieren
 * (--auswahl-schreiben in WSL, dann --csv im Container, siehe BETRIEB.md).
 *
 * Aufruf: php bin/import_inkar.php [--zip=DATEI | --csv=DATEI] [--ab-jahr=2015] [--auswahl-schreiben=DATEI] [--dry-run]
 *   --zip                INKAR-Gesamtdatei (Standard /srv/daten/inkar/inkar_2025.zip), braucht `unzip`
 *   --csv                stattdessen eine CSV im selben Format, auch .gz (z.B. der Auszug)
 *   --auswahl-schreiben  nur die benötigten Zeilen (+ Bundeswerte) als .csv.gz schreiben, keine DB
 *   --ab-jahr            ältere Jahre weglassen (Standard 2015)
 *   --dry-run            nur zählen und Beispielwerte zeigen, nichts schreiben
 *   --help               nur diese Hilfe
 *
 * Sicherheitsnetz: Das Ersetzen der Werte läuft in einer Transaktion und wird
 * zurückgerollt, wenn weniger als 100.000 Werte gelesen wurden.
 *
 * Quellvermerk: "Laufende Raumbeobachtung des BBSR - INKAR, Ausgabe 07/2025", dl-de/by-2.0
 */

require __DIR__ . '/../app/Support/Db.php';
require __DIR__ . '/../app/Support/InkarIndikatoren.php';

use App\Support\Db;
use App\Support\InkarIndikatoren;

$opt = getopt('', ['zip:', 'csv:', 'auswahl-schreiben:', 'ab-jahr:', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$zip = $opt['zip'] ?? '/srv/daten/inkar/inkar_2025.zip';
$csvDatei = $opt['csv'] ?? null;
$auswahlDatei = $opt['auswahl-schreiben'] ?? null;
$abJahr = (int) ($opt['ab-jahr'] ?? 2015);
$dryRun = isset($opt['dry-run']) || $auswahlDatei !== null;
$quelle = $csvDatei ?? $zip;
if (!is_file($quelle)) {
    fwrite(STDERR, "Datei fehlt: $quelle\n");
    exit(1);
}
const MINDEST_WERTE = 100000;

// INKAR-ID -> [kuerzel, ebene]
$gesucht = [];
foreach (InkarIndikatoren::LISTE as $kuerzel => $ind) {
    $gesucht[(string) $ind['id']] = [$kuerzel, $ind['ebene']];
}
$ebeneVon = ['Gemeinden' => 'GEM', 'Kreise' => 'KRS'];
$bund = []; // kuerzel -> jahr -> Wert für ganz Deutschland (Raumbezug "Bund", bevölkerungsgewichtet)

$db = $dryRun ? null : Db::get();
if ($db !== null) {
    $db->beginTransaction();
    $platzhalter = implode(',', array_fill(0, count(InkarIndikatoren::LISTE), '?'));
    $db->prepare("DELETE FROM inkar_werte WHERE kuerzel IN ($platzhalter)")->execute(array_keys(InkarIndikatoren::LISTE));
}
$puffer = [];
$schreiben = function () use (&$puffer, $db): void {
    if ($db === null || $puffer === []) {
        $puffer = [];
        return;
    }
    $sql = 'INSERT INTO inkar_werte (ebene, ags, kuerzel, jahr, wert) VALUES '
        . implode(',', array_fill(0, count($puffer), '(?,?,?,?,?)'))
        . ' ON DUPLICATE KEY UPDATE wert = VALUES(wert)';
    $db->prepare($sql)->execute(array_merge(...$puffer));
    $puffer = [];
};

$start = microtime(true);
// Gesamtdatei als Datenstrom über `unzip -p` (Deflate64), Auszug direkt (auch .gz).
if ($csvDatei !== null) {
    $csv = fopen('compress.zlib://' . $csvDatei, 'r');
} else {
    if (trim((string) shell_exec('command -v unzip')) === '') {
        fwrite(STDERR, "unzip fehlt (die INKAR-Zip nutzt Deflate64). Auszug aus WSL mit --csv importieren.\n");
        exit(1);
    }
    $csv = popen('unzip -p ' . escapeshellarg($zip) . ' inkar_2025.csv', 'r');
}
if ($csv === false) {
    fwrite(STDERR, "$quelle nicht lesbar\n");
    exit(1);
}
$kopf = fgets($csv);
$auswahl = null;
if ($auswahlDatei !== null) {
    $auswahl = gzopen($auswahlDatei, 'w6');
    gzwrite($auswahl, $kopf);
}
$zeilen = 0;
$treffer = [];
$beispiel = [];
while (($zeile = fgets($csv)) !== false) {
    $zeilen++;
    if ($zeilen % 10000000 === 0) {
        printf("  … %d Mio. Zeilen gelesen (%.0f s)\n", $zeilen / 1e6, microtime(true) - $start);
    }
    // Schnellfilter vor dem Zerlegen: >95 % der Zeilen betreffen andere Raumebenen.
    if (!str_contains($zeile, ';Gemeinden;') && !str_contains($zeile, ';Kreise;') && !str_contains($zeile, ';Bund;')) {
        continue;
    }
    $s = explode(';', rtrim($zeile, "\r\n"));
    $id = $s[1];
    if (!isset($gesucht[$id])) {
        continue;
    }
    [$kuerzel, $ebene] = $gesucht[$id];
    if ($s[4] === 'Bund') {
        if ($auswahl !== null) {
            gzwrite($auswahl, $zeile);
        }
        if ($s[count($s) - 1] !== '') {
            $bund[$kuerzel][(int) $s[count($s) - 2]] = (float) str_replace(',', '.', $s[count($s) - 1]);
        }
        continue;
    }
    if (($ebeneVon[$s[4]] ?? null) !== $ebene) {
        continue;
    }
    $jahr = (int) $s[count($s) - 2];
    $wertText = $s[count($s) - 1];
    if ($jahr < $abJahr || $wertText === '') {
        continue;
    }
    $kennziffer = str_pad($s[5], 8, '0', STR_PAD_LEFT);
    $ags = $ebene === 'KRS' ? substr($kennziffer, 0, 5) : $kennziffer;
    $wert = (float) str_replace(',', '.', $wertText);
    $treffer[$kuerzel] = ($treffer[$kuerzel] ?? 0) + 1;
    $beispiel[$kuerzel] ??= "$ags {$s[6]} $jahr: $wert";
    if ($auswahl !== null) {
        gzwrite($auswahl, $zeile);
    }
    $puffer[] = [$ebene, $ags, $kuerzel, $jahr, $wert];
    if (count($puffer) >= 2000) {
        $schreiben();
    }
}
$csvDatei !== null ? fclose($csv) : pclose($csv);
$schreiben();
if ($auswahl !== null) {
    gzclose($auswahl);
    printf("Auszug geschrieben: %s (%.1f MB)\n", $auswahlDatei, filesize($auswahlDatei) / 1048576);
}
$gesamt = array_sum($treffer);
if ($db !== null && $gesamt < MINDEST_WERTE) {
    $db->rollBack();
    fwrite(STDERR, "Nur $gesamt Werte gelesen (< " . MINDEST_WERTE . ") — abgebrochen, alte Werte bleiben unverändert.\n");
    exit(1);
}
if ($db !== null) {
    $db->commit();
}
printf("%d Zeilen gelesen in %.0f s\n", $zeilen, microtime(true) - $start);

foreach (InkarIndikatoren::LISTE as $kuerzel => $ind) {
    $b = $bund[$kuerzel] ?? [];
    printf("  %-20s %s %7d Werte  z.B. %-42s Deutschland %s\n", $kuerzel, $ind['ebene'], $treffer[$kuerzel] ?? 0, $beispiel[$kuerzel] ?? '— KEINE —',
        $b ? max(array_keys($b)) . ': ' . $b[max(array_keys($b))] : '—');
}

if ($db === null) {
    echo "Testlauf: nichts geschrieben.\n";
    exit(0);
}

// Neuestes Jahr + Bundes-Median je Indikator (MariaDB: MEDIAN() als Fensterfunktion)
$db->exec('DELETE FROM inkar_indikatoren');
$ins = $db->prepare('INSERT INTO inkar_indikatoren (kuerzel, ebene, inkar_id, jahr_neu, wert_bund, median, anzahl) VALUES (?,?,?,?,?,?,?)');
foreach (InkarIndikatoren::LISTE as $kuerzel => $ind) {
    $jahr = $db->prepare('SELECT MAX(jahr) FROM inkar_werte WHERE kuerzel = ?');
    $jahr->execute([$kuerzel]);
    $jahrNeu = $jahr->fetchColumn();
    if ($jahrNeu === null || $jahrNeu === false) {
        continue;
    }
    $med = $db->prepare('SELECT MEDIAN(wert) OVER (), COUNT(*) OVER () FROM inkar_werte WHERE kuerzel = ? AND jahr = ? LIMIT 1');
    $med->execute([$kuerzel, $jahrNeu]);
    [$median, $anzahl] = $med->fetch(PDO::FETCH_NUM);
    $ins->execute([$kuerzel, $ind['ebene'], $ind['id'], $jahrNeu, $bund[$kuerzel][(int) $jahrNeu] ?? null, $median, $anzahl]);
}
echo "inkar_indikatoren: neuestes Jahr, Deutschland-Wert und Median gespeichert.\n";
