<?php

namespace Sofnet\AmqpConnector;

class Message
{
    protected $origin;
    protected $destination;
    protected $type;
    protected $direction;
    protected $route;
    protected $body;

    public function __construct($origin, $destination, $type, $direction, $route, $body)
    {
        $this->origin = $origin;
        $this->destination = $destination;
        $this->type = $type;
        $this->direction = $direction;
        $this->route = $route;
        $this->body = $body;
    }

    public function getOrigin()
    {
        return $this->origin;
    }

    public function getDestination()
    {
        return $this->destination;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getDirection()
    {
        return $this->direction;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function getBody()
    {
        return $this->body;
    }
}
