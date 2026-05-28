<?php

declare(strict_types=1);

namespace App\Gateways;

final class DynamoDbProductRepository
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $tableName,
        private readonly string $region,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $seedPath,
    ) {
    }

    public function all(): array
    {
        try {
            $items = $this->scanTable();
        } catch (\Throwable) {
            $items = $this->loadSeedItems();
        }

        return $this->mapProducts($items);
    }

    public function tableName(): string
    {
        return $this->tableName;
    }

    private function scanTable(): array
    {
        $payload = json_encode(['TableName' => $this->tableName], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new \RuntimeException('No se pudo construir el payload DynamoDB.');
        }

        $response = $this->postDynamoDb('DynamoDB_20120810.Scan', $payload);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('DynamoDB respondio con JSON invalido.');
        }

        return is_array($decoded['Items'] ?? null) ? $decoded['Items'] : [];
    }

    private function postDynamoDb(string $target, string $payload): string
    {
        $headers = $this->signedHeaders($target, $payload);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 4,
            ],
        ]);

        $response = @file_get_contents($this->endpoint, false, $context);
        if ($response === false) {
            throw new \RuntimeException('No se pudo conectar con DynamoDB local.');
        }

        $statusCode = $this->httpStatusCode($http_response_header ?? []);
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('DynamoDB local respondio HTTP ' . $statusCode . '.');
        }

        return $response;
    }

    private function signedHeaders(string $target, string $payload): array
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST) ?: 'localhost';
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        $hostHeader = $port === null ? $host : $host . ':' . $port;

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $payload);
        $signedHeaders = 'host;x-amz-date;x-amz-target';
        $canonicalHeaders = implode("\n", [
            'host:' . $hostHeader,
            'x-amz-date:' . $amzDate,
            'x-amz-target:' . $target,
            '',
        ]);

        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $scope = $dateStamp . '/' . $this->region . '/dynamodb/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = hash_hmac(
            'sha256',
            $stringToSign,
            $this->signatureKey($dateStamp),
        );

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKey,
            $scope,
            $signedHeaders,
            $signature,
        );

        return [
            'Content-Type: application/x-amz-json-1.0',
            'X-Amz-Date: ' . $amzDate,
            'X-Amz-Target: ' . $target,
            'Authorization: ' . $authorization,
        ];
    }

    private function signatureKey(string $dateStamp): string
    {
        $dateKey = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $regionKey = hash_hmac('sha256', $this->region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 'dynamodb', $regionKey, true);

        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }

    private function httpStatusCode(array $headers): int
    {
        $statusLine = $headers[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function loadSeedItems(): array
    {
        if (!is_file($this->seedPath)) {
            return [];
        }

        $payload = json_decode(file_get_contents($this->seedPath) ?: '{}', true);
        if (!is_array($payload)) {
            return [];
        }

        $requests = $payload[$this->tableName] ?? $payload['productos'] ?? [];
        $items = [];

        foreach ($requests as $request) {
            $item = $request['PutRequest']['Item'] ?? null;
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function mapProducts(array $items): array
    {
        $products = [];

        foreach ($items as $item) {
            $record = $this->unmarshalItem($item);
            $pk = (string) ($record['PK'] ?? '');
            $sk = (string) ($record['SK'] ?? '');

            if (!str_starts_with($pk, 'PRODUCT#')) {
                continue;
            }

            $products[$pk] ??= [
                'id' => $pk,
                'name' => $pk,
                'category' => 'General',
                'brand' => '',
                'price' => 0,
                'stock' => 0,
                'image' => '/assets/products/phone.svg',
                'sort' => 999,
            ];

            if ($sk === 'INFO') {
                $products[$pk] = array_merge($products[$pk], [
                    'name' => (string) ($record['nombre'] ?? $record['name'] ?? $pk),
                    'category' => (string) ($record['categoria'] ?? 'General'),
                    'brand' => (string) ($record['marca'] ?? ''),
                    'price' => (int) ($record['precio'] ?? 0),
                    'image' => (string) ($record['imagen'] ?? '/assets/products/phone.svg'),
                    'sort' => (int) ($record['orden'] ?? 999),
                ]);
            }

            if ($sk === 'INVENTORY') {
                $products[$pk]['stock'] = (int) ($record['stock'] ?? 0);
            }
        }

        usort(
            $products,
            static fn (array $left, array $right): int => $left['sort'] <=> $right['sort']
        );

        return array_values($products);
    }

    private function unmarshalItem(array $item): array
    {
        $result = [];

        foreach ($item as $name => $value) {
            if (is_array($value)) {
                $result[$name] = $this->unmarshalValue($value);
            }
        }

        return $result;
    }

    private function unmarshalValue(array $value): mixed
    {
        if (array_key_exists('S', $value)) {
            return $value['S'];
        }

        if (array_key_exists('N', $value)) {
            return str_contains((string) $value['N'], '.')
                ? (float) $value['N']
                : (int) $value['N'];
        }

        if (array_key_exists('BOOL', $value)) {
            return (bool) $value['BOOL'];
        }

        if (array_key_exists('L', $value) && is_array($value['L'])) {
            return array_map(fn (array $item): mixed => $this->unmarshalValue($item), $value['L']);
        }

        if (array_key_exists('M', $value) && is_array($value['M'])) {
            return $this->unmarshalItem($value['M']);
        }

        return null;
    }
}
