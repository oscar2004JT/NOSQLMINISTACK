<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use function App\Http\errorResponse;
use function App\Http\jsonResponse;
use function App\Http\renderApp;
use function App\Http\serveStaticFile;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$requestedFile = realpath(__DIR__ . $path);
$publicRoot = realpath(__DIR__);

if (
    $method === 'GET'
    && $requestedFile !== false
    && $publicRoot !== false
    && str_starts_with($requestedFile, $publicRoot)
    && is_file($requestedFile)
    && $requestedFile !== __FILE__
) {
    return false;
}

if ($method === 'OPTIONS') {
    jsonResponse(204, []);
    return;
}

if (str_starts_with($path, '/static/')) {
    serveStaticFile($path);
    return;
}

try {
    if ($method === 'GET' && $path === '/api/productos') {
        get_products_lambda()->handle();
        return;
    }

    if ($method === 'GET' && preg_match('#^/api/usuarios/([^/]+)$#', $path, $matches)) {
        get_user_lambda()->handle($matches[1]);
        return;
    }

    if ($method === 'GET' && preg_match('#^/api/usuarios/([^/]+)/pedidos$#', $path, $matches)) {
        get_orders_lambda()->handle($matches[1]);
        return;
    }

    if ($method === 'GET' && preg_match('#^/api/usuarios/([^/]+)/pedidos/([^/]+)$#', $path, $matches)) {
        get_order_detail_lambda()->handle($matches[1], $matches[2]);
        return;
    }

    if ($method === 'POST' && preg_match('#^/api/usuarios/([^/]+)/pedidos$#', $path, $matches)) {
        create_order_lambda()->handle($matches[1]);
        return;
    }

    if ($method === 'GET' && ($path === '/' || $path === '/usuarios/123')) {
        renderApp('123', '555');
        return;
    }

    jsonResponse(404, ['error' => 'Ruta no encontrada.']);
} catch (Throwable $error) {
    errorResponse($error);
}
