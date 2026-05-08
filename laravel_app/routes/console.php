<?php

use App\Application\SampleData;
use App\Infrastructure\DynamoDbUserRepository;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mercado:seed-demo', function (DynamoDbUserRepository $repository) {
    $repository->createTableIfMissing();

    foreach (SampleData::items() as $item) {
        $repository->putItem($item);
    }

    $this->info('Datos demo cargados en DynamoDB local.');
})->purpose('Create and seed the MiMercado demo data in DynamoDB local');
