<?php

use App\Core\Request;
use App\Core\Router;

require_once __DIR__ . '/../bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$router = new Router();
require __DIR__ . '/../routes/web.php';

try {
    $router->dispatch(new Request());
} catch (\Throwable $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);

    if (config('app.debug', false)) {
        echo '<pre>' . e($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    } else {
        \App\Core\View::render('errors/500');
    }
}
