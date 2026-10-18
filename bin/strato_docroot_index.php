<?php
// Einstieg im Docroot auf Strato (bin/deploy_strato.sh kopiert diese Datei nach <docroot>/index.php).
// Eine echte Datei statt eines Symlinks auf current/public/index.php: OPcache kompiliert ein Skript einmal
// und friert dabei __FILE__ auf den damals aufgelösten Pfad ein. Mit einem Symlink hieß das (18.10.2026):
// nach dem Release-Wechsel lief noch stundenlang das alte Release weiter. Hier ist der Include-Pfad je
// Release verschieden (releases/<zeit>/…), also kompiliert OPcache jedes Release frisch.
$release = @readlink(__DIR__ . '/current');
if ($release === false) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Kein aktives Release (current fehlt).\n";
    exit;
}
require __DIR__ . '/' . $release . '/public/index.php';
