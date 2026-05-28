<?php

declare(strict_types=1);

namespace App\Business;

final class MercadoService
{
    public function __construct(private readonly MercadoRepository $repository)
    {
    }

    public function getUserData(string $userId): array
    {
        $profile = $this->repository->getUserProfile($userId);
        if ($profile === null) {
            throw new NotFoundError('Usuario no encontrado.');
        }

        $orders = $this->repository->getUserOrders($userId);
        $items = [];
        foreach ($orders as $order) {
            $items = array_merge($items, $this->repository->getOrderItems($userId, (string) $order['order_id']));
        }

        return [
            'perfil' => $profile,
            'pedidos' => $orders,
            'productos' => $items,
        ];
    }

    public function getOrders(string $userId): array
    {
        if ($this->repository->getUserProfile($userId) === null) {
            throw new NotFoundError('Usuario no encontrado.');
        }

        return ['pedidos' => $this->repository->getUserOrders($userId)];
    }

    public function getOrderDetail(string $userId, string $orderId): array
    {
        if ($this->repository->getUserProfile($userId) === null) {
            throw new NotFoundError('Usuario no encontrado.');
        }

        $order = $this->repository->getOrder($userId, $orderId);
        if ($order === null) {
            throw new NotFoundError('Pedido no encontrado.');
        }

        return [
            'pedido' => $order,
            'items' => $this->repository->getOrderItems($userId, $orderId),
        ];
    }

    public function createOrder(string $userId, array $payload): array
    {
        $profile = $this->repository->getUserProfile($userId);
        if ($profile === null) {
            throw new NotFoundError('Usuario no encontrado.');
        }

        $items = $payload['items'] ?? null;
        if (!is_array($items) || count($items) === 0) {
            throw new ValidationError("Debes enviar al menos un item en el campo 'items'.");
        }

        $orderId = (string) ($payload['order_id'] ?? time());
        $estado = (string) ($payload['estado'] ?? 'Pendiente');
        $fecha = (string) ($payload['fecha'] ?? gmdate('Y-m-d\TH:i:s\Z'));
        $direccion = (string) ($payload['direccion'] ?? ($profile['direcciones'][0] ?? ''));

        if (trim($direccion) === '') {
            throw new ValidationError('La direccion del pedido es obligatoria.');
        }

        $itemRecords = [];
        $total = 0;

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                throw new ValidationError('Cada item debe ser un objeto JSON.');
            }

            $producto = trim((string) ($item['producto'] ?? ''));
            $cantidad = (int) ($item['cantidad'] ?? 0);
            $precio = (int) ($item['precio'] ?? 0);

            if ($producto === '') {
                throw new ValidationError("Cada item debe incluir 'producto'.");
            }
            if ($cantidad <= 0) {
                throw new ValidationError("Cada item debe incluir una 'cantidad' mayor que cero.");
            }
            if ($precio < 0) {
                throw new ValidationError("Cada item debe incluir un 'precio' valido.");
            }

            $itemId = (string) ($item['item_id'] ?? ($index + 1));
            $subtotal = $cantidad * $precio;
            $total += $subtotal;

            $itemRecords[] = [
                'PK' => "USER#{$userId}",
                'SK' => "ORDER#{$orderId}#ITEM#{$itemId}",
                'Tipo' => 'ITEM',
                'producto' => $producto,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'subtotal' => $subtotal,
            ];
        }

        $created = $this->repository->createOrder($userId, [
            'PK' => "USER#{$userId}",
            'SK' => "ORDER#{$orderId}",
            'Tipo' => 'ORDER',
            'estado' => $estado,
            'fecha' => $fecha,
            'direccion' => $direccion,
            'total' => $total,
        ], $itemRecords);

        return ['message' => 'Pedido creado correctamente.'] + $created;
    }
}
