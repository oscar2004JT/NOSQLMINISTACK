<?php

declare(strict_types=1);

namespace App\Http;

function renderApp(string $userId, string $featuredOrderId): void
{
    header('Content-Type: text/html; charset=utf-8');
    $userIdJson = json_encode($userId, JSON_UNESCAPED_UNICODE);
    $featuredOrderIdJson = json_encode($featuredOrderId, JSON_UNESCAPED_UNICODE);

    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoCart</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <div id="root"></div>

    <script>
        window.APP_CONFIG = {
            userId: {$userIdJson},
            featuredOrderId: {$featuredOrderIdJson}
        };
    </script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script crossorigin src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script type="text/babel" src="/app.jsx"></script>
</body>
</html>
HTML;
}

function serveStaticFile(string $path): void
{
    $relative = ltrim(substr($path, strlen('/static/')), '/');
    $file = realpath(dirname(__DIR__, 2) . '/static/' . $relative);
    $staticRoot = realpath(dirname(__DIR__, 2) . '/static');

    if ($file === false || $staticRoot === false || !str_starts_with($file, $staticRoot) || !is_file($file)) {
        http_response_code(404);
        echo 'Archivo no encontrado.';
        return;
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'jsx' => 'text/jsx; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];

    header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
    readfile($file);
}
