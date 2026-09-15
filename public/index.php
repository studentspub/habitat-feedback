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
    require __DIR__ . '/map.view.php';
    return;
}

if ($uri === '/bewerten') {
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
