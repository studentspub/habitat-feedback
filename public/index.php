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

http_response_code(404);
echo 'Nicht gefunden.';
