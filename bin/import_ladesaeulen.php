<?php
/**
 * Import des Ladesäulenregisters der Bundesnetzagentur als POI-Kategorie
 * "charging" in poi_locations (Issue #22). Nur Ladeeinrichtungen mit Status
 * "In Betrieb". In poi_locations steht für diese Kategorie in osm_id die
 * Ladeeinrichtungs-ID der BNetzA (osm_type = 'node'), nicht eine OSM-ID.
 *
 * Quelle: https://www.bundesnetzagentur.de/ladesaeulenkarte -> "Liste der Ladesäulen (CSV)"
 * (UTF-8 mit BOM, 10 Zeilen Vorspann, Semikolon, Dezimalkomma).
 * Lizenz CC BY 4.0, Namensnennung "Bundesnetzagentur.de".
 *
 * Aufruf: php bin/import_ladesaeulen.php [--csv=DATEI] [--url=URL] [--dry-run]
 *   --csv      lokale CSV (Standard: neueste Ladesaeulenregister_*.csv in /srv/daten/ladesaeulen)
 *   --url      stattdessen herunterladen (nach /srv/daten/ladesaeulen) und importieren
 *   --dry-run  nur lesen und zählen, nichts schreiben
 *   --help     nur diese Hilfe
 */

require __DIR__ . '/../app/Support/Db.php';

use App\Support\Db;

const ORDNER = '/srv/daten/ladesaeulen';
const MINDEST = 10000;

$opt = getopt('', ['csv:', 'url:', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$dryRun = isset($opt['dry-run']);
$datei = $opt['csv'] ?? null;
if (isset($opt['url'])) {
    $datei = ORDNER . '/' . basename(parse_url($opt['url'], PHP_URL_PATH));
    if (file_put_contents($datei, file_get_contents($opt['url'])) === false) {
        fwrite(STDERR, "Download fehlgeschlagen: {$opt['url']}\n");
        exit(1);
    }
}
if ($datei === null) {
    $kandidaten = glob(ORDNER . '/Ladesaeulenregister_*.csv') ?: [];
    sort($kandidaten);
    $datei = end($kandidaten) ?: null;
}
if ($datei === null || !is_file($datei)) {
    fwrite(STDERR, "Keine CSV gefunden (--csv oder --url angeben).\n");
    exit(1);
}

$fh = fopen($datei, 'r');
$kopf = null;
while (($zeile = fgetcsv($fh, 0, ';', '"', '')) !== false) {
    $zeile[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $zeile[0]);
    if ($zeile[0] === 'Ladeeinrichtungs-ID') {
        $kopf = array_flip($zeile);
        break;
    }
}
if ($kopf === null) {
    fwrite(STDERR, "Kopfzeile 'Ladeeinrichtungs-ID;…' nicht gefunden in $datei\n");
    exit(1);
}
foreach (['Ladeeinrichtungs-ID', 'Betreiber', 'Anzeigename (Karte)', 'Status', 'Art der Ladeeinrichtung', 'Anzahl Ladepunkte', 'Nennleistung Ladeeinrichtung [kW]', 'Breitengrad', 'Längengrad'] as $spalte) {
    if (!isset($kopf[$spalte])) {
        fwrite(STDERR, "Spalte '$spalte' fehlt — Format geändert?\n");
        exit(1);
    }
}

$db = $dryRun ? null : Db::get();
$ins = null;
if ($db !== null) {
    $db->beginTransaction();
    $db->exec("DELETE FROM poi_locations WHERE category = 'charging'");
    $ins = $db->prepare("INSERT INTO poi_locations (category, osm_id, osm_type, lat, lon, name) VALUES ('charging', ?, 'node', ?, ?, ?)
        ON DUPLICATE KEY UPDATE lat = VALUES(lat), lon = VALUES(lon), name = VALUES(name)");
}
$n = $schnell = $nichtInBetrieb = 0;
while (($s = fgetcsv($fh, 0, ';', '"', '')) !== false) {
    if (count($s) < count($kopf) - 5 || !ctype_digit((string) $s[$kopf['Ladeeinrichtungs-ID']])) {
        continue;
    }
    if ($s[$kopf['Status']] !== 'In Betrieb') {
        $nichtInBetrieb++;
        continue;
    }
    $lat = (float) str_replace(',', '.', $s[$kopf['Breitengrad']]);
    $lon = (float) str_replace(',', '.', $s[$kopf['Längengrad']]);
    if ($lat < 47 || $lat > 55.2 || $lon < 5.5 || $lon > 15.5) {
        continue; // offensichtlich falsche Koordinaten
    }
    $istSchnell = str_contains($s[$kopf['Art der Ladeeinrichtung']], 'Schnell');
    $schnell += $istSchnell ? 1 : 0;
    $betreiber = trim($s[$kopf['Anzeigename (Karte)']]) !== '' ? trim($s[$kopf['Anzeigename (Karte)']]) : trim($s[$kopf['Betreiber']]);
    $punkte = (int) $s[$kopf['Anzahl Ladepunkte']];
    $kw = (float) str_replace(',', '.', $s[$kopf['Nennleistung Ladeeinrichtung [kW]']]);
    $name = mb_substr(sprintf('%s · %d Ladepunkt%s, %s kW%s', $betreiber, $punkte, $punkte === 1 ? '' : 'e',
        rtrim(rtrim(number_format($kw, 1, ',', ''), '0'), ','), $istSchnell ? ' (Schnellladen)' : ''), 0, 200);
    $ins?->execute([(int) $s[$kopf['Ladeeinrichtungs-ID']], $lat, $lon, $name]);
    $n++;
}
fclose($fh);

if ($db !== null) {
    if ($n < MINDEST) {
        $db->rollBack();
        fwrite(STDERR, "Nur $n Ladeeinrichtungen gelesen (< " . MINDEST . ") — abgebrochen, alte Daten bleiben.\n");
        exit(1);
    }
    $db->commit();
}
printf("%s: %d Ladeeinrichtungen in Betrieb (davon %d Schnellladen), %d nicht in Betrieb übersprungen%s\n",
    basename($datei), $n, $schnell, $nichtInBetrieb, $dryRun ? ' — Testlauf, nichts geschrieben' : '');
