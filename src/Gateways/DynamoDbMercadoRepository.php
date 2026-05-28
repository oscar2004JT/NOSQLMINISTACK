<?php

declare(strict_types=1);

namespace App\Gateways;

use App\Business\MercadoRepository;

final class DynamoDbMercadoRepository implements MercadoRepository
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $tableName,
        private readonly string $region,
        private readonly string $accessKey,
        private readonly string $secretKey,
    ) {
    }

    public function getUserProfile(string $userId): ?array
    {
        foreach ($this->queryUserPartition($userId) as $item) {
            if (($item['Tipo'] ?? null) === 'USER') {
                return [
                    'user_id' => $userId,
                    'nombre' => $item['nombre'] ?? null,
                    'email' => $item['email'] ?? null,
                    'direcciones' => $item['direcciones'] ?? [],
                    'pagos' => $item['pagos'] ?? [],
                ];
            }
        }

        return null;
    }

    public function getUserOrders(string $userId): array
    {
        $orders = [];
        foreach ($this->queryUserPartition($userId) as $record) {
            if (($record['Tipo'] ?? null) !== 'ORDER') {
                continue;
            }

            $orders[] = [
                'user_id' => $userId,
                'order_id' => str_replace('ORDER#', '', (string) $record['SK']),
                'estado' => $record['estado'] ?? null,
                'fecha' => $record['fecha'] ?? null,
                'direccion' => $record['direccion'] ?? null,
                'total' => $this->toInt($record['total'] ?? 0),
            ];
        }

        usort($orders, static fn (array $a, array $b): int => $a['order_id'] <=> $b['order_id']);
        return $orders;
    }

    public function getOrder(string $userId, string $orderId): ?array
    {
        foreach ($this->queryOrderRecords($userId, $orderId) as $record) {
            if (($record['SK'] ?? null) !== "ORDER#{$orderId}") {
                continue;
            }

            return [
                'user_id' => $userId,
                'order_id' => $orderId,
                'estado' => $record['estado'] ?? null,
                'fecha' => $record['fecha'] ?? null,
                'direccion' => $record['direccion'] ?? null,
                'total' => $this->toInt($record['total'] ?? 0),
            ];
        }

        return null;
    }

    public function getOrderItems(string $userId, string $orderId): array
    {
        $items = [];
        $prefix = "ORDER#{$orderId}#ITEM#";

        foreach ($this->queryOrderRecords($userId, $orderId) as $record) {
            if (!str_starts_with((string) ($record['SK'] ?? ''), $prefix)) {
                continue;
            }

            $skParts = explode('#', (string) $record['SK']);
            $items[] = [
                'user_id' => $userId,
                'order_id' => $orderId,
                'item_id' => end($skParts),
                'producto' => $record['producto'] ?? null,
                'cantidad' => $this->toInt($record['cantidad'] ?? 0),
                'precio' => $this->toInt($record['precio'] ?? 0),
                'subtotal' => $this->toInt($record['subtotal'] ?? 0),
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['item_id'] <=> $b['item_id']);
        return $items;
    }

    public function createOrder(string $userId, array $orderRecord, array $itemRecords): array
    {
        foreach (array_merge([$orderRecord], $itemRecords) as $record) {
            $this->putRecord($record);
        }

        $orderId = str_replace('ORDER#', '', (string) $orderRecord['SK']);

        return [
            'pedido' => $this->getOrder($userId, $orderId),
            'items' => $this->getOrderItems($userId, $orderId),
        ];
    }

    private function queryUserPartition(string $userId): array
    {
        return $this->queryRecords(
            'PK = :pk',
            [':pk' => "USER#{$userId}"]
        );
    }

    private function queryOrderRecords(string $userId, string $orderId): array
    {
        return $this->queryRecords(
            'PK = :pk AND begins_with(SK, :sk)',
            [
                ':pk' => "USER#{$userId}",
                ':sk' => "ORDER#{$orderId}",
            ]
        );
    }

    private function queryRecords(string $keyCondition, array $values): array
    {
        $payload = [
            'TableName' => $this->tableName,
            'KeyConditionExpression' => $keyCondition,
            'ExpressionAttributeValues' => $this->marshalItem($values),
        ];

        $items = [];
        do {
            $decoded = $this->postDynamoDb('DynamoDB_20120810.Query', $payload);
            foreach (($decoded['Items'] ?? []) as $item) {
                if (is_array($item)) {
                    $items[] = $this->unmarshalItem($item);
                }
            }

            $payload['ExclusiveStartKey'] = $decoded['LastEvaluatedKey'] ?? null;
        } while (is_array($payload['ExclusiveStartKey']));

        return $items;
    }

    private function putRecord(array $record): void
    {
        $this->postDynamoDb('DynamoDB_20120810.PutItem', [
            'TableName' => $this->tableName,
            'Item' => $this->marshalItem($record),
        ]);
    }

    private function postDynamoDb(string $target, array $payload): array
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('No se pudo construir el payload DynamoDB.');
        }

        $headers = $this->signedHeaders($target, $encoded);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $encoded,
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

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('DynamoDB respondio con JSON invalido.');
        }

        return $decoded;
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

        $signature = hash_hmac('sha256', $stringToSign, $this->signatureKey($dateStamp));

        return [
            'Content-Type: application/x-amz-json-1.0',
            'X-Amz-Date: ' . $amzDate,
            'X-Amz-Target: ' . $target,
            'Authorization: AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope
                . ', SignedHeaders=' . $signedHeaders
                . ', Signature=' . $signature,
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

    private function marshalItem(array $item): array
    {
        $result = [];
        foreach ($item as $key => $value) {
            $result[$key] = $this->marshalValue($value);
        }

        return $result;
    }

    private function marshalValue(mixed $value): array
    {
        if (is_int($value) || is_float($value)) {
            return ['N' => (string) $value];
        }

        if (is_bool($value)) {
            return ['BOOL' => $value];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return ['L' => array_map(fn (mixed $item): array => $this->marshalValue($item), $value)];
            }

            return ['M' => $this->marshalItem($value)];
        }

        if ($value === null) {
            return ['NULL' => true];
        }

        return ['S' => (string) $value];
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

        if (array_key_exists('NULL', $value)) {
            return null;
        }

        if (array_key_exists('L', $value) && is_array($value['L'])) {
            return array_map(fn (array $item): mixed => $this->unmarshalValue($item), $value['L']);
        }

        if (array_key_exists('M', $value) && is_array($value['M'])) {
            return $this->unmarshalItem($value['M']);
        }

        return null;
    }

    private function toInt(mixed $value): int
    {
        return (int) ($value ?: 0);
    }
}
