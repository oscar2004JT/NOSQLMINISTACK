<?php

declare(strict_types=1);

namespace App\Business;

interface MercadoRepository
{
    public function getUserProfile(string $userId): ?array;

    public function getUserOrders(string $userId): array;

    public function getOrder(string $userId, string $orderId): ?array;

    public function getOrderItems(string $userId, string $orderId): array;

    public function createOrder(string $userId, array $orderRecord, array $itemRecords): array;
}
