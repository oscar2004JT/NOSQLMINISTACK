<?php

declare(strict_types=1);

use App\Business\MercadoError;

const PRODUCTS_CACHE_TTL_SECONDS = 60;
const PRODUCTS_CACHE_KEY = 'ecocart:productos';

$bootstrapPath = first_existing_path([
    __DIR__ . '/src/bootstrap.php',
    __DIR__ . '/../../../src/bootstrap.php',
]);

require_once $bootstrapPath;

$event = read_event($argv[1] ?? null);

echo json_encode(handle_event($event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function handle_event(array $event): array
{
    $method = request_method($event);
    $path = request_path($event);

    try {
        if ($method === 'OPTIONS') {
            return response(204, '');
        }

        if ($method === 'GET' && in_array($path, ['/', '/ecommerce', '/index.html'], true)) {
            return html_response(render_app());
        }

        if ($method === 'GET' && preg_match('#^/(app\.jsx|styles\.css|assets/products/[^/]+\.svg)$#', $path, $matches) === 1) {
            return file_response($matches[1]);
        }

        if ($method === 'GET' && $path === '/api/productos') {
            return cached_products_response();
        }

        if ($method === 'GET' && preg_match('#^/api/usuarios/([^/]+)$#', $path, $matches) === 1) {
            return json_response(200, mercado_service()->getUserData(rawurldecode($matches[1])));
        }

        if ($method === 'GET' && preg_match('#^/api/usuarios/([^/]+)/pedidos$#', $path, $matches) === 1) {
            return json_response(200, mercado_service()->getOrders(rawurldecode($matches[1])));
        }

        if ($method === 'GET' && preg_match('#^/api/usuarios/([^/]+)/pedidos/([^/]+)$#', $path, $matches) === 1) {
            return json_response(
                200,
                mercado_service()->getOrderDetail(rawurldecode($matches[1]), rawurldecode($matches[2]))
            );
        }

        if ($method === 'POST' && preg_match('#^/api/usuarios/([^/]+)/pedidos$#', $path, $matches) === 1) {
            return json_response(
                201,
                mercado_service()->createOrder(rawurldecode($matches[1]), request_json_body($event))
            );
        }

        return json_response(404, ['error' => 'Ruta no encontrada.']);
    } catch (Throwable $error) {
        if ($error instanceof MercadoError) {
            return json_response($error->statusCode(), ['error' => $error->getMessage()]);
        }

        error_log((string) $error);
        return json_response(500, ['error' => 'Error interno no controlado.']);
    }
}

function cached_products_response(): array
{
    $cached = read_products_cache();
    if ($cached !== null) {
        return json_response(200, $cached['payload'], [
            'X-Cache' => 'HIT',
            'X-Cache-Store' => $cached['store'],
        ]);
    }

    $products = product_repository();
    $payload = [
        'table' => $products->tableName(),
        'items' => $products->all(),
    ];

    $cacheStore = write_products_cache($payload);

    return json_response(200, $payload, [
        'X-Cache' => 'MISS',
        'X-Cache-Store' => $cacheStore,
    ]);
}

function read_products_cache(): ?array
{
    $redisPayload = redis_get(PRODUCTS_CACHE_KEY);
    if ($redisPayload !== null) {
        $payload = json_decode($redisPayload, true);
        if (is_array($payload) && products_cache_payload_is_valid($payload)) {
            return [
                'payload' => $payload,
                'store' => 'redis',
            ];
        }
    }

    if (!is_file(products_cache_file())) {
        return null;
    }

    $cache = json_decode(file_get_contents(products_cache_file()) ?: '{}', true);
    if (!is_array($cache)) {
        return null;
    }

    $expiresAt = (int) ($cache['expires_at'] ?? 0);
    $payload = $cache['payload'] ?? null;

    if ($expiresAt <= time() || !is_array($payload) || !products_cache_payload_is_valid($payload)) {
        return null;
    }

    return [
        'payload' => $payload,
        'store' => 'file',
    ];
}

function write_products_cache(array $payload): string
{
    $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    if (redis_setex(PRODUCTS_CACHE_KEY, PRODUCTS_CACHE_TTL_SECONDS, $encodedPayload)) {
        return 'redis';
    }

    $cache = [
        'expires_at' => time() + PRODUCTS_CACHE_TTL_SECONDS,
        'payload' => $payload,
    ];

    file_put_contents(
        products_cache_file(),
        json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        LOCK_EX
    );

    return 'file';
}

function products_cache_payload_is_valid(array $payload): bool
{
    return isset($payload['table'], $payload['items']) && is_array($payload['items']);
}

function products_cache_file(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ecocart-products-cache.json';
}

function redis_get(string $key): ?string
{
    $response = redis_command(['GET', $key]);

    return is_string($response) ? $response : null;
}

function redis_setex(string $key, int $ttlSeconds, string $value): bool
{
    return redis_command(['SETEX', $key, (string) $ttlSeconds, $value]) === 'OK';
}

function redis_command(array $parts): mixed
{
    $host = getenv('REDIS_HOST') ?: '';
    if ($host === '') {
        return null;
    }

    $port = (int) (getenv('REDIS_PORT') ?: 6379);
    $socket = @fsockopen($host, $port, $errno, $errstr, 0.2);
    if (!is_resource($socket)) {
        return null;
    }

    stream_set_timeout($socket, 1);
    fwrite($socket, redis_encode_command($parts));
    $response = redis_read_response($socket);
    fclose($socket);

    return $response;
}

function redis_encode_command(array $parts): string
{
    $command = '*' . count($parts) . "\r\n";
    foreach ($parts as $part) {
        $part = (string) $part;
        $command .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
    }

    return $command;
}

function redis_read_response($socket): mixed
{
    $line = fgets($socket);
    if ($line === false || $line === '') {
        return null;
    }

    $prefix = $line[0];
    $payload = rtrim(substr($line, 1), "\r\n");

    if ($prefix === '+') {
        return $payload;
    }

    if ($prefix === '-') {
        error_log('Redis error: ' . $payload);
        return null;
    }

    if ($prefix === ':') {
        return (int) $payload;
    }

    if ($prefix !== '$') {
        return null;
    }

    $length = (int) $payload;
    if ($length < 0) {
        return null;
    }

    $value = '';
    while (strlen($value) < $length) {
        $chunk = fread($socket, $length - strlen($value));
        if ($chunk === false || $chunk === '') {
            return null;
        }
        $value .= $chunk;
    }

    fread($socket, 2);

    return $value;
}

function read_event(?string $eventFile): array
{
    if ($eventFile !== null && is_readable($eventFile)) {
        $payload = file_get_contents($eventFile) ?: '';
    } else {
        $payload = stream_get_contents(STDIN) ?: '';
    }

    $event = json_decode(ltrim($payload, "\xEF\xBB\xBF"), true);

    return is_array($event) ? $event : [];
}

function request_method(array $event): string
{
    return strtoupper((string) (
        $event['httpMethod']
        ?? $event['requestContext']['http']['method']
        ?? 'GET'
    ));
}

function request_path(array $event): string
{
    $path = (string) (
        $event['path']
        ?? $event['rawPath']
        ?? $event['requestContext']['http']['path']
        ?? '/'
    );

    $path = parse_url($path, PHP_URL_PATH) ?: '/';
    $path = '/' . ltrim($path, '/');

    return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
}

function request_json_body(array $event): array
{
    $body = (string) ($event['body'] ?? '{}');
    if (($event['isBase64Encoded'] ?? false) === true) {
        $decodedBody = base64_decode($body, true);
        $body = $decodedBody === false ? '' : $decodedBody;
    }

    $payload = json_decode($body === '' ? '{}' : $body, true);
    if (!is_array($payload)) {
        throw new App\Business\ValidationError('El cuerpo de la solicitud debe ser JSON valido.');
    }

    return $payload;
}

function render_app(): string
{
    return <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoCart</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div id="root"></div>

    <script>
        window.APP_CONFIG = {
            userId: "123",
            featuredOrderId: "555"
        };
    </script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script crossorigin src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script type="text/babel" src="app.jsx"></script>
</body>
</html>
HTML;
}

function file_response(string $relativePath): array
{
    $file = first_existing_path([
        __DIR__ . '/public/' . $relativePath,
        __DIR__ . '/../../../public/' . $relativePath,
    ]);

    $contentType = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'css' => 'text/css; charset=utf-8',
        'jsx' => 'text/babel; charset=utf-8',
        'svg' => 'image/svg+xml; charset=utf-8',
        default => 'application/octet-stream',
    };

    return response(200, file_get_contents($file) ?: '', ['Content-Type' => $contentType]);
}

function first_existing_path(array $paths): string
{
    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    throw new RuntimeException('No se encontro el archivo requerido.');
}

function html_response(string $body): array
{
    return response(200, $body, ['Content-Type' => 'text/html; charset=utf-8']);
}

function json_response(int $statusCode, array $body, array $headers = []): array
{
    return response(
        $statusCode,
        json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ['Content-Type' => 'application/json; charset=utf-8'] + $headers
    );
}

function response(int $statusCode, string $body, array $headers = []): array
{
    return [
        'statusCode' => $statusCode,
        'headers' => [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,X-Amz-Date',
            'Access-Control-Allow-Methods' => 'GET,POST,OPTIONS',
        ] + $headers,
        'body' => $body,
        'isBase64Encoded' => false,
    ];
}
