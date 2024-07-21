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
        $timeout = config('amqp.timeout');

        if (empty($channel) || empty($host) || empty($port) || empty($login) || empty($password)) {
            throw new \Exception('Invalid configuration');
        }

        if (empty($timeout) or !is_int($timeout) or $timeout < 0) {
            $timeout = 5;
        }

        $this->amqpClient->setTimeout($timeout);
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
