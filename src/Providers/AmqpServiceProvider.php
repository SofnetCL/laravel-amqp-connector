<?php

namespace Sofnet\AmqpConnector\Providers;

use Illuminate\Support\ServiceProvider;
use Sofnet\AmqpConnector\Services\AmqpClient;
use Sofnet\AmqpConnector\Routing\Router;
use Sofnet\AmqpConnector\Console\Commands\ConsumeMessages;
use Sofnet\AmqpConnector\Services\Amqp;

class AmqpServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Router::class, function ($app) {
            return new Router();
        });

        $this->app->singleton(Amqp::class, function ($app) {
            $amqpClient = new AmqpClient($app->make(Router::class));
            return new Amqp($amqpClient);
        });

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
    }
}
