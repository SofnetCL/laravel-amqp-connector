<?php

namespace Sofnet\AmqpConnector\Routing;

class RouteGroup
{
    protected $prefix;
    protected $syncRoutes;
    protected $asyncRoutes;

    public function __construct($prefix)
    {
        $this->prefix = $prefix;
        $this->syncRoutes = [];
        $this->asyncRoutes = [];
    }

    public function sync($queue, callable $callback)
    {
        $route = new Route("{$this->prefix}.{$queue}", $callback);
        $this->syncRoutes[] = $route;
    }

    public function async($queue, callable $callback)
    {
        $route = new Route("{$this->prefix}.{$queue}", $callback);
        $this->asyncRoutes[] = $route;
    }

    public function getRoutes()
    {
        return array_merge($this->syncRoutes, $this->asyncRoutes);
    }
}
