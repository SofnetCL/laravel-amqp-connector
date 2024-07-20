<?php

namespace Sofnet\AmqpConnector;

class Response
{
    protected $origin;
    protected $destination;
    protected $body;
    protected $type;
    protected $route;
    protected $correlationId;

    public function __construct($origin, $destination, $body, $type, $route, $correlationId = null)
    {
        $this->origin = $origin;
        $this->destination = $destination;
        $this->body = $body;
        $this->type = $type;
        $this->route = $route;
        $this->correlationId = $correlationId;
    }

    public function getOrigin()
    {
        return $this->origin;
    }

    public function getDestination()
    {
        return $this->destination;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function getCorrelationId()
    {
        return $this->correlationId;
    }
}
