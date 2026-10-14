<?php

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, strlen('App\\'));
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Sicherheits-Header für alle Antworten (Issue #9). Die Content-Security-Policy
// gilt bisher für die Kartenseite (Route '/', JS ausgelagert in #13) und das
// Bewertungsformular ('/bewerten', #15); die übrigen Seiten haben noch Inline-Skripte.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$method = $_SERVER['REQUEST_METHOD'];

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

if ($uri === '/' || $uri === '/index.php') {
    // Nur eigene Skripte; Styles brauchen 'unsafe-inline' (Leaflet und die
    // Panels setzen style-Attribute). Externe Ziele: OSM-Kacheln, Breitband-WMS,
    // Lärm (EEA) und Hochwasser (BfG) als WMS (Issue #21). Zensus kommt seit #23 aus der eigenen DB. Ein eingeschleustes <script> liefe damit nicht —
    // das Sicherheitsnetz hinter escapeHtml() (Issue #9).
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: https://*.tile.openstreetmap.org https://breitbandatlas-wms.gigabit-grundbuch.online "
        . "https://copernicus.discomap.eea.europa.eu https://geoportal.bafg.de; "
        . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    require __DIR__ . '/map.view.php';
    return;
}

if ($uri === '/bewerten') {
    // Nur eigenes JS (public/js/bewerten.js) und Anfragen an den eigenen Server — auch die Straßen-Vorschläge (#15).
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
        . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    require __DIR__ . '/bewerten.view.php';
    return;
}

if ($uri === '/melden') {
    require __DIR__ . '/melden.view.php';
    return;
}

if ($uri === '/impressum') {
    require __DIR__ . '/impressum.view.php';
    return;
}

if ($uri === '/datenschutz') {
    require __DIR__ . '/datenschutz.view.php';
    return;
}

if ($uri === '/ueber') {
    require __DIR__ . '/ueber.view.php';
    return;
}

if ($uri === '/vision') {
    require __DIR__ . '/vision.view.php';
    return;
}

http_response_code(404);
echo 'Nicht gefunden.';
