<?php

namespace Sofnet\AmqpConnector\Providers;

use Illuminate\Support\ServiceProvider;
use Sofnet\AmqpConnector\Services\AmqpClient;
use Sofnet\AmqpConnector\Routing\Router;
use Sofnet\AmqpConnector\Console\Commands\ConsumeMessages;

class AmqpServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Router::class, function ($app) {
            return new Router();
        });

        $this->app->singleton(AmqpClient::class, function ($app) {
            $config = $app->make('config')->get('amqp');
            $appChannel = $config['channel'];

            return new AmqpClient($app->make(Router::class), $appChannel);
        });

        $this->app->alias(Router::class, 'amqp-router');
        $this->commands([ConsumeMessages::class]);
    }

    public function boot()
    {
        if (file_exists($file = __DIR__ . '/../helpers.php')) {
            require $file;
        }

        $this->publishes([
            __DIR__ . '/../config/amqp.php' => config_path('amqp.php'),
        ], 'config');

        $client = $this->app->make(AmqpClient::class);
        $config = $this->app->make('config')->get('amqp');
        $client->connect($config['host'], $config['port'], $config['login'], $config['password']);
    }
}
