<?php

namespace Sofnet\AmqpConnector\Facades;

use Illuminate\Support\Facades\Facade;
use Sofnet\AmqpConnector\Routing\Router;

/**
 * @see \Sofnet\AmqpConnector\Routing\Router
 */
class Route extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Router::class;
    }

    public static function async(string $route, callable $callback)
    {
        $router = static::getFacadeRoot();
        return $router->async($route, $callback);
    }

    public static function sync(string $route, callable $callback)
    {
        $router = static::getFacadeRoot();
        return $router->sync($route, $callback);
    }
}
