<?php

namespace Sofnet\AmqpConnector\Services;

use Sofnet\AmqpConnector\Request;
use Sofnet\AmqpConnector\Response;

class Amqp
{
    private AmqpClient $amqpClient;

    public function __construct(AmqpClient $amqpClient)
    {
        $this->amqpClient = $amqpClient;
    }

    public function connect()
    {
        $channel = config('amqp.channel');
        $host = config('amqp.host');
        $port = config('amqp.port');
        $login = config('amqp.login');
        $password = config('amqp.password');

        $this->amqpClient->connect($host, $port, $login, $password, $channel);
    }

    public function consumeMessages(): void
    {
        $this->amqpClient->consumeMessage();
    }

    public function publishMessage(string $channel, Request $request): void
    {
        $this->amqpClient->publishMessage($channel, $request);
    }

    public function sendSyncMessage(string $channel, Request $request): Response
    {
        return $this->amqpClient->sendSyncMessage($channel, $request);
    }
}
