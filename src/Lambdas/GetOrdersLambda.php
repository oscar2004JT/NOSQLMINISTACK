<?php

declare(strict_types=1);

namespace App\Lambdas;

use App\Business\MercadoService;

use function App\Http\jsonResponse;

final class GetOrdersLambda
{
    public function __construct(private readonly MercadoService $service)
    {
    }

    public function handle(string $userId): void
    {
        jsonResponse(200, $this->service->getOrders($userId));
    }
}
