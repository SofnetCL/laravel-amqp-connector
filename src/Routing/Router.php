<?php

namespace Sofnet\AmqpConnector\Routing;

use Sofnet\AmqpConnector\Request;

class Router
{
    protected $routes = [];

    public function group($prefix, callable $callback)
    {
        $group = new RouteGroup($prefix);
        call_user_func($callback, $group);

        foreach ($group->getRoutes() as $route) {
            $this->routes[$route->getQueue()] = $route;
        }
    }

    public function route(Request $request)
    {
        $queue = $request->getRoute();
        $type = $request->getType();

        if (isset($this->routes[$queue])) {
            $route = $this->routes[$queue];
            return $route->dispatch($request);
        }

        throw new \Exception("No route found for type {$type} and route {$queue}");
    }
}
