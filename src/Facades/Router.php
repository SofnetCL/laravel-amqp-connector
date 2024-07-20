<?php

namespace Sofnet\AmqpConnector\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Sofnet\AmqpConnector\Routing\Router
 */
class Router extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'amqp-router';
    }
}
