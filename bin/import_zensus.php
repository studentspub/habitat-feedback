<?php
/**
 * Import der Zensus-2022-Gitterdaten (Destatis) in zensus_gitter (Issue #23):
 * Einwohner, Durchschnittsalter, durchschnittliche Nettokaltmiete/m²,
 * Eigentümerquote, Leerstandsquote — je im 100-m-, 1-km- und 10-km-Gitter.
 * Ersetzt den Live-Abruf beim ArcGIS-Dienst von Esri (USA).
 *
 * Quelle: https://www.zensus2022.de/DE/Ergebnisse-des-Zensus/gitterzellen.html
 * (Zips unter https://www.destatis.de/static/DE/zensus/gitterdaten/, CSV mit
 * Semikolon, Dezimalkomma, "–" = kein Wert). Lizenz dl-de/by-2.0.
 * Gelesen über PHPs ZipArchive (geht auch im Container ohne unzip).
 *
 * Voraussetzung: Migration sql/023_zensus_gitter.sql
 *
 * Aufruf: php bin/import_zensus.php [--cache=DIR] [--nur=100m,1km,10km] [--dry-run]
 *   --cache    Ordner für die Zips (Standard /srv/daten/zensus), fehlende werden geladen
 *   --nur      nur diese Rasterstufen (Standard alle drei)
 *   --dry-run  nur lesen und zählen, nichts schreiben
 *   --help     nur diese Hilfe
 */

require __DIR__ . '/../app/Support/Db.php';

use App\Support\Db;

const BASIS = 'https://www.destatis.de/static/DE/zensus/gitterdaten/';
// Spalte in zensus_gitter => [Zip-Name, Merkmal im Dateinamen, Spalte in der CSV]
const MERKMALE = [
    'einwohner' => ['Zensus2022_Bevoelkerungszahl', 'Bevoelkerungszahl', 'Einwohner'],
    'alter_d' => ['Durchschnittsalter_in_Gitterzellen', 'Durchschnittsalter', 'Durchschnittsalter'],
    'miete_qm' => ['Zensus2022_Durchschn_Nettokaltmiete', 'Durchschn_Nettokaltmiete', 'durchschnMieteQM'],
    'eigentum' => ['Eigentuemerquote_in_Gitterzellen', 'Eigentuemerquote', 'Eigentuemerquote'],
    'leerstand' => ['Leerstandsquote_in_Gitterzellen', 'Leerstandsquote', 'Leerstandsquote'],
];
const STUFEN = ['100m' => 100, '1km' => 1000, '10km' => 10000];

$opt = getopt('', ['cache:', 'nur:', 'dry-run', 'help']);
if (isset($opt['help'])) {
    preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), "\n";
    exit(0);
}
$cache = rtrim($opt['cache'] ?? '/srv/daten/zensus', '/');
$dryRun = isset($opt['dry-run']);
$stufen = isset($opt['nur']) ? array_intersect_key(STUFEN, array_flip(explode(',', $opt['nur']))) : STUFEN;
if ($stufen === []) {
    fwrite(STDERR, "--nur: erlaubt sind 100m, 1km, 10km\n");
    exit(1);
}

$db = $dryRun ? null : Db::get();
$start = microtime(true);
foreach ($stufen as $stufe => $groesse) {
    if ($db !== null) {
        $db->beginTransaction();
        $db->prepare('DELETE FROM zensus_gitter WHERE aufloesung = ?')->execute([$groesse]);
    }
    foreach (MERKMALE as $spalte => [$zipName, $merkmal, $csvSpalte]) {
        $zipDatei = "$cache/$zipName.zip";
        if (!is_file($zipDatei)) {
            $inhalt = @file_get_contents(BASIS . "$zipName.zip");
            if ($inhalt === false) {
                fwrite(STDERR, "Download fehlgeschlagen: $zipName.zip\n");
                exit(1);
            }
            file_put_contents($zipDatei, $inhalt);
        }
        $zip = new ZipArchive();
        if ($zip->open($zipDatei) !== true) {
            fwrite(STDERR, "Zip nicht lesbar: $zipDatei\n");
            exit(1);
        }
        $csv = $zip->getStream("Zensus2022_{$merkmal}_{$stufe}-Gitter.csv");
        if ($csv === false) {
            fwrite(STDERR, "Zensus2022_{$merkmal}_{$stufe}-Gitter.csv fehlt in $zipName.zip\n");
            exit(1);
        }
        $kopf = array_flip(explode(';', preg_replace('/^\xEF\xBB\xBF/', '', trim(fgets($csv)))));
        $ix = $kopf["x_mp_$stufe"] ?? null;
        $iy = $kopf["y_mp_$stufe"] ?? null;
        $iw = $kopf[$csvSpalte] ?? null;
        if ($ix === null || $iy === null || $iw === null) {
            fwrite(STDERR, "Spalten x_mp_$stufe / y_mp_$stufe / $csvSpalte fehlen in $merkmal $stufe\n");
            exit(1);
        }

        // Einwohner legt die Zellen an, die übrigen Merkmale ergänzen sie (Upsert).
        $puffer = [];
        $n = 0;
        $flush = function () use (&$puffer, $db, $spalte, $groesse): void {
            if ($db === null || $puffer === []) {
                $puffer = [];
                return;
            }
            $sql = "INSERT INTO zensus_gitter (aufloesung, x, y, kx, ky, $spalte) VALUES "
                . implode(',', array_fill(0, count($puffer), '(?,?,?,?,?,?)'))
                . " ON DUPLICATE KEY UPDATE $spalte = VALUES($spalte)";
            $db->prepare($sql)->execute(array_merge(...$puffer));
            $puffer = [];
        };
        while (($z = fgets($csv)) !== false) {
            $s = explode(';', rtrim($z, "\r\n"));
            if (!isset($s[$iw])) {
                continue;
            }
            $text = trim($s[$iw]);
            if ($text === '' || $text === '–' || $text === '-') {
                continue;
            }
            $x = (int) $s[$ix];
            $y = (int) $s[$iy];
            $wert = $spalte === 'einwohner' ? (int) $text : (float) str_replace(',', '.', $text);
            $puffer[] = [$groesse, $x, $y, intdiv($x, 10000), intdiv($y, 10000), $wert];
            $n++;
            if (count($puffer) >= 2000) {
                $flush();
            }
        }
        $flush();
        fclose($csv);
        $zip->close();
        printf("  %-5s %-10s %8d Zellen  (%.0f s)\n", $stufe, $spalte, $n, microtime(true) - $start);
        if ($spalte === 'einwohner' && $n < 1000 && $db !== null) {
            $db->rollBack();
            fwrite(STDERR, "Nur $n Zellen für $stufe — abgebrochen, alte Werte bleiben.\n");
            exit(1);
        }
    }
    $db?->commit();
}
echo $dryRun ? "Testlauf: nichts geschrieben.\n" : sprintf("Fertig in %.0f s.\n", microtime(true) - $start);
