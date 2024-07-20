<?php

namespace Sofnet\AmqpConnector\Routing;

class RouteCollection
{
    protected $routes = [];

    public function addRoute(Route $route)
    {
        $this->routes[$route->getQueue()] = $route;
    }

    public function addRoutes(array $routes)
    {
        foreach ($routes as $route) {
            $this->addRoute($route);
        }
    }

    public function hasRoute($queue)
    {
        return isset($this->routes[$queue]);
    }

    public function getRoute($queue)
    {
        return $this->routes[$queue] ?? null;
    }

    public function getAllRoutes()
    {
        return $this->routes;
    }
}
