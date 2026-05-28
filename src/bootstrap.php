<?php

declare(strict_types=1);

require_once __DIR__ . '/Business/Errors.php';
require_once __DIR__ . '/Business/MercadoRepository.php';
require_once __DIR__ . '/Business/MercadoService.php';
require_once __DIR__ . '/Gateways/DynamoDbMercadoRepository.php';
require_once __DIR__ . '/Gateways/DynamoDbProductRepository.php';
require_once __DIR__ . '/Gateways/JsonMercadoRepository.php';
require_once __DIR__ . '/Http/Response.php';
require_once __DIR__ . '/Http/WebPage.php';
require_once __DIR__ . '/Lambdas/GetProductsLambda.php';
require_once __DIR__ . '/Lambdas/GetUserLambda.php';
require_once __DIR__ . '/Lambdas/GetOrdersLambda.php';
require_once __DIR__ . '/Lambdas/GetOrderDetailLambda.php';
require_once __DIR__ . '/Lambdas/CreateOrderLambda.php';

use App\Business\MercadoService;
use App\Gateways\DynamoDbMercadoRepository;
use App\Gateways\DynamoDbProductRepository;
use App\Gateways\JsonMercadoRepository;
use App\Lambdas\CreateOrderLambda;
use App\Lambdas\GetProductsLambda;
use App\Lambdas\GetOrderDetailLambda;
use App\Lambdas\GetOrdersLambda;
use App\Lambdas\GetUserLambda;

function mercado_service(): MercadoService
{
    if (getenv('DYNAMODB_ENDPOINT') || getenv('AWS_ENDPOINT_URL') || getenv('TABLE_NAME') || getenv('USERS_TABLE_NAME')) {
        return new MercadoService(new DynamoDbMercadoRepository(
            getenv('DYNAMODB_ENDPOINT') ?: getenv('AWS_ENDPOINT_URL') ?: 'http://localhost:4566',
            getenv('TABLE_NAME') ?: getenv('USERS_TABLE_NAME') ?: 'ecommerce-local-orders',
            getenv('AWS_REGION') ?: getenv('AWS_DEFAULT_REGION') ?: 'us-east-1',
            getenv('AWS_ACCESS_KEY_ID') ?: 'test',
            getenv('AWS_SECRET_ACCESS_KEY') ?: 'test',
        ));
    }

    $root = dirname(__DIR__);
    $dataPath = getenv('MERCADO_DATA_PATH') ?: $root . '/storage/mercado_data.json';
    $seedPath = getenv('MERCADO_SEED_PATH') ?: $root . '/app/data/mercado_seed.json';

    return new MercadoService(new JsonMercadoRepository($dataPath, $seedPath));
}

function product_repository(): DynamoDbProductRepository
{
    $root = dirname(__DIR__);

    return new DynamoDbProductRepository(
        getenv('DYNAMODB_ENDPOINT') ?: getenv('AWS_ENDPOINT_URL') ?: 'http://localhost:4566',
        getenv('PRODUCTS_TABLE_NAME') ?: 'productos',
        getenv('AWS_REGION') ?: getenv('AWS_DEFAULT_REGION') ?: 'us-east-1',
        getenv('AWS_ACCESS_KEY_ID') ?: 'test',
        getenv('AWS_SECRET_ACCESS_KEY') ?: 'test',
        $root . '/infra/seeds/productos.json',
    );
}

function get_products_lambda(): GetProductsLambda
{
    return new GetProductsLambda(product_repository());
}

function get_user_lambda(): GetUserLambda
{
    return new GetUserLambda(mercado_service());
}

function get_orders_lambda(): GetOrdersLambda
{
    return new GetOrdersLambda(mercado_service());
}

function get_order_detail_lambda(): GetOrderDetailLambda
{
    return new GetOrderDetailLambda(mercado_service());
}

function create_order_lambda(): CreateOrderLambda
{
    return new CreateOrderLambda(mercado_service());
}
