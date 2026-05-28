<?php

declare(strict_types=1);

namespace App\Lambdas;

use App\Gateways\DynamoDbProductRepository;

use function App\Http\jsonResponse;

final class GetProductsLambda
{
    public function __construct(private readonly DynamoDbProductRepository $products)
    {
    }

    public function handle(): void
    {
        jsonResponse(200, [
            'table' => $this->products->tableName(),
            'items' => $this->products->all(),
        ]);
    }
}
