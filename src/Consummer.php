<?php

namespace Sofnet\AmqpConnector;

use Sofnet\AmqpConnector\Facades\AmqpConsumer;

abstract class Consummer
{
    abstract function getChannel(): string;

    public function dispatch(string $route, $body): void
    {
        AmqpConsumer::dispatch($this->getChannel(), $route, $body);
    }

    public function get(string $route, $body): Response
    {
        return AmqpConsumer::get($this->getChannel(), $route, $body);
    }
}
