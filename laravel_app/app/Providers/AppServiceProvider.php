<?php

namespace App\Providers;

use App\Application\MercadoQueryService;
use App\Contracts\UserRepository;
use App\Infrastructure\DynamoDbUserRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Marshaler::class, function () {
            return new Marshaler();
        });

        $this->app->singleton(DynamoDbClient::class, function () {
            return new DynamoDbClient([
                'version' => 'latest',
                'region' => config('dynamodb.region'),
                'endpoint' => config('dynamodb.endpoint'),
                'credentials' => [
                    'key' => config('dynamodb.key'),
                    'secret' => config('dynamodb.secret'),
                ],
            ]);
        });

        $this->app->singleton(UserRepository::class, function ($app) {
            return new DynamoDbUserRepository(
                client: $app->make(DynamoDbClient::class),
                marshaller: $app->make(Marshaler::class),
                tableName: config('dynamodb.table'),
                cache: $app->make(CacheFactory::class)->store(),
            );
        });

        $this->app->singleton(DynamoDbUserRepository::class, function ($app) {
            return $app->make(UserRepository::class);
        });

        $this->app->singleton(MercadoQueryService::class, function ($app) {
            return new MercadoQueryService($app->make(UserRepository::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
