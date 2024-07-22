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

    public function connectRpc()
    {
        $channel = config('amqp.channel');
        $channel_rpc = $channel . '_rpc';
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
        $this->amqpClient->connectRpc($host, $port, $login, $password, $channel_rpc);
    }

    public function connectQueue()
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
        $this->amqpClient->connectQueue($host, $port, $login, $password, $channel);
    }

    public function consumeQueueMessages(): void
    {
        $this->amqpClient->consumeQueueMessages();
    }

    public function consumeRpcMessages(): void
    {
        $this->amqpClient->consumeRpcMessages();
    }

    public function dispatchQueueMessage(string $channel, Request $request): void
    {
        $this->amqpClient->dispatchQueueMessage($channel, $request);
    }

    public function sendSyncMessage(string $channel, Request $request): Response
    {
        return $this->amqpClient->sendSyncMessage($channel, $request);
    }
}
