<?php

declare(strict_types=1);

namespace App\Gateways;

use App\Business\MercadoRepository;

final class JsonMercadoRepository implements MercadoRepository
{
    public function __construct(
        private readonly string $dataPath,
        private readonly string $seedPath
    ) {
        $this->ensureDataFile();
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
        $record = $this->findByKey("USER#{$userId}", "ORDER#{$orderId}");
        if ($record === null) {
            return null;
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

    public function getOrderItems(string $userId, string $orderId): array
    {
        $items = [];
        $prefix = "ORDER#{$orderId}#ITEM#";

        foreach ($this->queryUserPartition($userId) as $record) {
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
        $records = $this->readRecords();
        $upserts = array_merge([$orderRecord], $itemRecords);

        foreach ($upserts as $newRecord) {
            $updated = false;
            foreach ($records as $index => $record) {
                if (($record['PK'] ?? null) === $newRecord['PK'] && ($record['SK'] ?? null) === $newRecord['SK']) {
                    $records[$index] = $newRecord;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $records[] = $newRecord;
            }
        }

        $this->writeRecords($records);
        $orderId = str_replace('ORDER#', '', (string) $orderRecord['SK']);

        return [
            'pedido' => $this->getOrder($userId, $orderId),
            'items' => $this->getOrderItems($userId, $orderId),
        ];
    }

    private function queryUserPartition(string $userId): array
    {
        $pk = "USER#{$userId}";
        return array_values(array_filter(
            $this->readRecords(),
            static fn (array $item): bool => ($item['PK'] ?? null) === $pk
        ));
    }

    private function findByKey(string $pk, string $sk): ?array
    {
        foreach ($this->readRecords() as $item) {
            if (($item['PK'] ?? null) === $pk && ($item['SK'] ?? null) === $sk) {
                return $item;
            }
        }

        return null;
    }

    private function ensureDataFile(): void
    {
        if (is_file($this->dataPath)) {
            return;
        }

        $directory = dirname($this->dataPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        copy($this->seedPath, $this->dataPath);
    }

    private function readRecords(): array
    {
        $contents = file_get_contents($this->dataPath);
        $records = json_decode($contents === false ? '[]' : $contents, true);

        if (!is_array($records)) {
            throw new \RuntimeException('El archivo de datos JSON no es valido.');
        }

        return $records;
    }

    private function writeRecords(array $records): void
    {
        $encoded = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || file_put_contents($this->dataPath, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('No se pudo guardar el pedido.');
        }
    }

    private function toInt(mixed $value): int
    {
        return (int) ($value ?: 0);
    }
}
