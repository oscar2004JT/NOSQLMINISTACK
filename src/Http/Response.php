<?php

declare(strict_types=1);

namespace App\Http;

use App\Business\MercadoError;
use App\Business\ValidationError;

function jsonResponse(int $statusCode, array $body): void
{
    http_response_code($statusCode);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type,Authorization');
    header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
}

function parseJsonBody(): array
{
    $rawBody = file_get_contents('php://input') ?: '{}';
    $payload = json_decode($rawBody, true);

    if (!is_array($payload)) {
        throw new ValidationError('El cuerpo de la solicitud debe ser JSON valido.');
    }

    return $payload;
}

function errorResponse(\Throwable $error): void
{
    if ($error instanceof MercadoError) {
        jsonResponse($error->statusCode(), ['error' => $error->getMessage()]);
        return;
    }

    error_log((string) $error);
    jsonResponse(500, ['error' => 'Error interno no controlado.']);
}
