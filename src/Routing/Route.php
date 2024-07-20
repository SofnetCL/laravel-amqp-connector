<?php

namespace Sofnet\AmqpConnector\Routing;

class Route
{
    protected $queue;
    protected $callback;

    public function __construct($queue, callable $callback)
    {
        $this->queue = $queue;
        $this->callback = $callback;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function dispatch($request)
    {
        return call_user_func($this->callback, $request);
    }
}
