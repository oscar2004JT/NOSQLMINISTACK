<?php

namespace App\Infrastructure;

use App\Contracts\UserRepository;
use App\Domain\Entities\Order;
use App\Domain\Entities\OrderItem;
use App\Domain\Entities\UserProfile;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class DynamoDbUserRepository implements UserRepository
{
    private const ORDERS_CACHE_TTL_SECONDS = 60;

    public function __construct(
        private DynamoDbClient $client,
        private Marshaler $marshaller,
        private string $tableName,
        private CacheRepository $cache,
    ) {
    }

    public function getUserProfile(string $userId): ?UserProfile
    {
        $items = $this->queryUserPartition($userId);
        $profile = collect($items)->firstWhere('Tipo', 'USER');

        if ($profile === null) {
            return null;
        }

        return new UserProfile(
            userId: $userId,
            nombre: $profile['nombre'],
            email: $profile['email'],
            direcciones: $profile['direcciones'] ?? [],
            pagos: $profile['pagos'] ?? [],
        );
    }

    public function getUserOrders(string $userId): array
    {
        return collect($this->getCachedOrderRecords($userId))
            ->map(fn (array $item) => new Order(
                userId: $userId,
                orderId: str_replace('ORDER#', '', $item['SK']),
                estado: $item['estado'],
                fecha: $item['fecha'],
                direccion: $item['direccion'],
                total: $item['total'],
            ))
            ->values()
            ->all();
    }

    public function getOrder(string $userId, string $orderId): ?Order
    {
        $order = collect($this->getCachedOrderRecords($userId))
            ->firstWhere('SK', 'ORDER#'.$orderId);

        if ($order === null) {
            return null;
        }

        return new Order(
            userId: $userId,
            orderId: $orderId,
            estado: $order['estado'],
            fecha: $order['fecha'],
            direccion: $order['direccion'],
            total: $order['total'],
        );
    }

    public function createTableIfMissing(): void
    {
        $existingTables = $this->client->listTables()->toArray();
        if (in_array($this->tableName, $existingTables['TableNames'] ?? [], true)) {
            return;
        }

        $this->client->createTable([
            'TableName' => $this->tableName,
            'KeySchema' => [
                ['AttributeName' => 'PK', 'KeyType' => 'HASH'],
                ['AttributeName' => 'SK', 'KeyType' => 'RANGE'],
            ],
            'AttributeDefinitions' => [
                ['AttributeName' => 'PK', 'AttributeType' => 'S'],
                ['AttributeName' => 'SK', 'AttributeType' => 'S'],
            ],
            'BillingMode' => 'PAY_PER_REQUEST',
        ]);
    }

    public function putItem(array $item): void
    {
        $this->client->putItem([
            'TableName' => $this->tableName,
            'Item' => $this->marshaller->marshalItem($item),
        ]);

        //$this->forgetOrderCachesForItem($item);
    }

    private function queryOrderItems(string $userId, string $orderId): array
    {
        $response = $this->client->query([
            'TableName' => $this->tableName,
            'KeyConditionExpression' => 'PK = :pk AND begins_with(SK, :sk)',
            'ExpressionAttributeValues' => [
                ':pk' => ['S' => 'USER#'.$userId],
                ':sk' => ['S' => 'ORDER#'.$orderId.'#ITEM#'],
            ],
        ])->toArray();

        return array_map(
            fn (array $item) => $this->marshaller->unmarshalItem($item),
            $response['Items'] ?? []
        );
    }

    private function forgetOrderCachesForItem(array $item): void
    {
        $type = $item['Tipo'] ?? null;
        $partitionKey = $item['PK'] ?? null;
        $sortKey = $item['SK'] ?? null;

        if (! in_array($type, ['ORDER', 'ITEM'], true) || ! is_string($partitionKey) || ! is_string($sortKey)) {
            return;
        }

        $userId = str_replace('USER#', '', $partitionKey);
        $this->cache->forget($this->ordersCacheKey($userId));

        if ($type === 'ORDER') {
            $orderId = str_replace('ORDER#', '', $sortKey);
            $this->cache->forget($this->orderItemsCacheKey($userId, $orderId));

            return;
        }

        $parts = explode('#', $sortKey);
        if (count($parts) < 2 || $parts[0] !== 'ORDER') {
            return;
        }

        $this->cache->forget($this->orderItemsCacheKey($userId, $parts[1]));
    }

    private function ordersCacheKey(string $userId): string
    {
        return "mercado:user:{$userId}:orders";
    }

    private function orderItemsCacheKey(string $userId, string $orderId): string
    {
        return "mercado:user:{$userId}:order:{$orderId}:items";
    }

    private function queryUserPartition(string $userId): array
    {
        $response = $this->client->query([
            'TableName' => $this->tableName,
            'KeyConditionExpression' => 'PK = :pk',
            'ExpressionAttributeValues' => [
                ':pk' => ['S' => 'USER#'.$userId],
            ],
        ])->toArray();

        return array_map(
            fn (array $item) => $this->marshaller->unmarshalItem($item),
            $response['Items'] ?? []
        );
    }





    // cache reed through.......................................................
    private function getCachedOrderRecords(string $userId): array
    {
        return $this->cache->remember(
            $this->ordersCacheKey($userId),
            now()->addSeconds(self::ORDERS_CACHE_TTL_SECONDS),
            fn () => collect($this->queryUserPartition($userId))
                ->where('Tipo', 'ORDER')
                ->values()
                ->all()
        );
    }

    
    public function getOrderItems(string $userId, string $orderId): array
    {
        $items = $this->cache->remember(
            $this->orderItemsCacheKey($userId, $orderId),
            now()->addSeconds(self::ORDERS_CACHE_TTL_SECONDS),
            fn () => $this->queryOrderItems($userId, $orderId)
        );

        return array_map(function (array $item) use ($userId, $orderId) {
            $parts = explode('#', $item['SK']);

            return new OrderItem(
                userId: $userId,
                orderId: $orderId,
                itemId: (string) end($parts),
                producto: $item['producto'],
                cantidad: $item['cantidad'],
                precio: $item['precio'],
                subtotal: $item['subtotal'],
            );
        }, $items);
    }

}
