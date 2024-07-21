<?php

namespace Sofnet\AmqpConnector\Routing;

use Sofnet\AmqpConnector\Request;

class Router
{
    protected $syncRoutes = [];
    protected $asyncRoutes = [];


    public function asyncGroup($prefix, callable $callback)
    {
        $group = new RouteGroup($prefix);
        call_user_func($callback, $group);

        foreach ($group->getRoutes() as $route) {
            $this->addRoute($route, 'async');
        }
    }

    public function syncGroup($prefix, callable $callback)
    {
        $group = new RouteGroup($prefix);
        call_user_func($callback, $group);

        foreach ($group->getRoutes() as $route) {
            $this->addRoute($route, 'sync');
        }
    }

    public function sync($queue, callable $callback)
    {
        $this->addRoute(new Route($queue, $callback), 'sync');
    }

    public function async($queue, callable $callback)
    {
        $this->addRoute(new Route($queue, $callback), 'async');
    }

    protected function addRoute(Route $route, $type)
    {
        if ($type === 'sync') {
            $this->syncRoutes[$route->getQueue()] = $route;
        } else {
            $this->asyncRoutes[$route->getQueue()] = $route;
        }
    }

    public function route(Request $request)
    {
        $queue = $request->getRoute();
        $type = $request->getType();
        $routes = $type === 'sync' ? $this->syncRoutes : $this->asyncRoutes;

        if (isset($routes[$queue])) {
            $route = $routes[$queue];
            return $route->dispatch($request);
        }

        throw new \Exception("No route found for type {$type} and route {$queue}");
    }
}
