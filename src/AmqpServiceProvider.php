<?php

namespace Sofnet\AmqpConnector\Providers;

use Illuminate\Support\ServiceProvider;
use Sofnet\AmqpConnector\Services\AmqpClient;
use Sofnet\AmqpConnector\Routing\Router;

class AmqpServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Router::class, function ($app) {
            return new Router();
        });

        $this->app->singleton(AmqpClient::class, function ($app) {
            $config = $app->make('config')->get('amqp');
            $appQueue = $config['queue'];

            $client = new AmqpClient($app->make(Router::class), $appQueue);
            $client->setConnectionfromConfig($config);

            return $client;
        });

        $this->app->alias(Router::class, 'amqp-router');
    }

    public function boot()
    {
        if (file_exists($file = __DIR__ . '/../helpers.php')) {
            require $file;
        }

        $this->publishes([
            __DIR__ . '/../config/amqp.php' => config_path('amqp.php'),
        ], 'config');
    }
}
