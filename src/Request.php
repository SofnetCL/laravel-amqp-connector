<?php

namespace Sofnet\AmqpConnector;

class Request
{
    protected $origin;
    protected $destination;
    protected $body;
    protected $type;
    protected $route;
    protected $correlationId;
    protected $replyTo;

    const ASYNC = 'async';
    const SYNC = 'sync';

    public function __construct($origin, $destination, $body, $type, $route)
    {
        $this->origin = $origin;
        $this->destination = $destination;
        $this->body = $body;
        $this->type = $type;
        $this->route = $route;
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

    public function setCorrelationId($correlationId)
    {
        $this->correlationId = $correlationId;
    }

    public function setReplyTo($replyTo)
    {
        $this->replyTo = $replyTo;
    }

    public function getCorrelationId()
    {
        return $this->correlationId;
    }

    public function getReplyTo()
    {
        return $this->replyTo;
    }

    public function createResponse($body)
    {
        return new Response(
            $this->destination,
            $this->origin,
            $body,
            $this->type,
            $this->route,
            $this->correlationId
        );
    }
}
