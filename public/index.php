<?php

// Echter Ordner dieser Datei. Auf Strato (#36) ist index.php im Docroot ein Symlink auf
// releases/<…>/public/index.php; __DIR__ zeigt dann auf den Docroot, nicht auf public/,
// und app/ läge "daneben" statt eine Ebene über public/. realpath löst den Symlink auf;
// lokal und im Container ist es derselbe Pfad wie __DIR__.
// clearstatcache: PHP merkt sich aufgelöste Pfade bis zu 120 s (realpath_cache_ttl); ohne das Löschen
// würde nach einem Deploy noch zwei Minuten lang das alte Release ausgeliefert (beobachtet 18.10.2026).
clearstatcache(true, __FILE__);
define('PUBLIC_DIR', dirname(realpath(__FILE__)));

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, strlen('App\\'));
    $path = PUBLIC_DIR . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Sicherheits-Header für alle Antworten (Issue #9, #30).
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

// Content-Security-Policy für alle HTML-Seiten (Issue #9, #13, #30): nur eigene
// Skripte - seit #30 hat keine Seite mehr Inline-JS -, Styles inline erlaubt
// (Leaflet und die Panels setzen style-Attribute), Anfragen nur an den eigenen
// Server. Ein eingeschleustes <script> liefe damit nicht: das Sicherheitsnetz
// hinter escapeHtml(). $bildQuellen: externe Kachel-/WMS-Hosts, die nur die
// beiden Kartenseiten brauchen (OSM-Kacheln, Breitband-WMS, Lärm EEA,
// Hochwasser BfG, Issue #21; Zensus kommt seit #23 aus der eigenen DB).
function csp(string $bildQuellen = ''): void
{
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:" . ($bildQuellen !== '' ? ' ' . $bildQuellen : '') . "; "
        . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}
const KARTEN_BILDQUELLEN = 'https://*.tile.openstreetmap.org https://breitbandatlas-wms.gigabit-grundbuch.online '
    . 'https://copernicus.discomap.eea.europa.eu https://geoportal.bafg.de';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$method = $_SERVER['REQUEST_METHOD'];
// HEAD wie GET behandeln (Issue #31): Monitoring und `curl -I` bekamen auf den
// API-Routen sonst 404. Den Body lässt PHP bei HEAD von selbst weg.
if ($method === 'HEAD') {
    $method = 'GET';
}

if ($uri === '/api/map' && $method === 'GET') {
    (new App\Http\MapApiController())->pins();
    return;
}

if (preg_match('#^/api/segments/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new App\Http\MapApiController())->detail((int) $m[1]);
    return;
}

if ($uri === '/api/ratings' && $method === 'POST') {
    (new App\Http\RatingController())->submit();
    return;
}

if ($uri === '/api/search' && $method === 'GET') {
    (new App\Http\SearchController())->search();
    return;
}

if ($uri === '/api/address-autocomplete' && $method === 'GET') {
    (new App\Http\AddressAutocompleteController())->search();
    return;
}

if ($uri === '/api/poi' && $method === 'GET') {
    (new App\Http\PoiController())->search();
    return;
}

if ($uri === '/api/climate' && $method === 'GET') {
    (new App\Http\ClimateController())->nearest();
    return;
}

if ($uri === '/api/air-quality' && $method === 'GET') {
    (new App\Http\AirQualityController())->nearest();
    return;
}

if ($uri === '/api/oepnv' && $method === 'GET') {
    (new App\Http\OepnvController())->umkreis();
    return;
}

if ($uri === '/api/oepnv/punkte' && $method === 'GET') {
    (new App\Http\OepnvController())->punkte();
    return;
}

if ($uri === '/api/zensus' && $method === 'GET') {
    (new App\Http\ZensusController())->zellen();
    return;
}

if ($uri === '/api/unfaelle' && $method === 'GET') {
    (new App\Http\UnfallController())->umkreis();
    return;
}

if ($uri === '/api/unfaelle/punkte' && $method === 'GET') {
    (new App\Http\UnfallController())->punkte();
    return;
}

if ($uri === '/api/region' && $method === 'GET') {
    (new App\Http\RegionController())->forLocation();
    return;
}

if ($uri === '/api/economy' && $method === 'GET') {
    (new App\Http\EconomyController())->forLocation();
    return;
}

if ($uri === '/api/reports' && $method === 'POST') {
    (new App\Http\ReportController())->submit();
    return;
}

if ($uri === '/api/pi/queue' && $method === 'GET') {
    (new App\Http\PiController())->queue();
    return;
}

if ($uri === '/api/pi/resolve' && $method === 'POST') {
    (new App\Http\PiController())->resolve();
    return;
}

// HTML-Seiten: Route => [View, Bildquellen für die CSP]. Die beiden Kartenseiten
// laden Kacheln und WMS-Bilder von außen, alle anderen nur eigene Bilder.
$seiten = [
    '/' => ['map.view.php', KARTEN_BILDQUELLEN],
    '/index.php' => ['map.view.php', KARTEN_BILDQUELLEN],
    '/vision' => ['vision.view.php', KARTEN_BILDQUELLEN],
    '/bewerten' => ['bewerten.view.php', ''],
    '/melden' => ['melden.view.php', ''],
    '/impressum' => ['impressum.view.php', ''],
    '/datenschutz' => ['datenschutz.view.php', ''],
    '/ueber' => ['ueber.view.php', ''],
    '/quellen' => ['quellen.view.php', ''],
    '/projekt' => ['projekt.view.php', ''],
];
// Besuchszähler (habitat-feedback-betrieb#8): erst die Seite ausliefern, dann zählen, damit der Besucher nicht
// auf die Datenbank wartet. Nur echte GET-Aufrufe, kein HEAD (Monitoring, curl -I).
function zaehlen(string $pfad): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    App\Support\Besuche::zaehlen($pfad);
}

if (isset($seiten[$uri])) {
    [$view, $bildQuellen] = $seiten[$uri];
    csp($bildQuellen);
    require PUBLIC_DIR . '/' . $view;
    zaehlen($uri === '/index.php' ? '/' : $uri);
    return;
}

http_response_code(404);
echo 'Nicht gefunden.';
// Mit Pfad (ohne Query), damit Scanner und kaputte Links unterscheidbar sind (habitat-feedback-betrieb#9).
// Nur druckbares ASCII: fremde Bytes würden das INSERT in utf8mb4 scheitern lassen.
zaehlen('404:' . preg_replace('/[^\x21-\x7E]/', '?', (string) $uri));
