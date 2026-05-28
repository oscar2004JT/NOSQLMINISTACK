<?php

declare(strict_types=1);

namespace App\Lambdas;

use App\Business\MercadoService;

use function App\Http\jsonResponse;
use function App\Http\parseJsonBody;

final class CreateOrderLambda
{
    public function __construct(private readonly MercadoService $service)
    {
    }

    public function handle(string $userId): void
    {
        jsonResponse(201, $this->service->createOrder($userId, parseJsonBody()));
    }
}
