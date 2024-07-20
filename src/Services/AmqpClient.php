<?php

namespace Sofnet\AmqpConnector\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use Sofnet\AmqpConnector\Routing\Router;
use Sofnet\AmqpConnector\Request;
use Sofnet\AmqpConnector\Response;

class AmqpClient
{
    /** @var AMQPChannel[] */
    protected array $channels = [];

    /** @var AMQPStreamConnection */
    protected $connection;

    /** @var Router */
    protected $router;

    /** @var string */
    protected $queueName;

    public function __construct(Router $router, $queueName)
    {
        $this->router = $router;
        $this->queueName = $queueName;
    }

    public function setConnectionfromConfig($config): void
    {
        $this->connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['login'],
            $config['password']
        );
        $this->createChannel($this->queueName);
        $this->consumeMessage($this->queueName, $this->queueName);
    }

    public function setConnection(AMQPStreamConnection $connection): void
    {
        $this->connection = $connection;
        $this->createChannel($this->queueName);
        $this->consumeMessage($this->queueName, $this->queueName);
    }

    public function getConnection(): AMQPStreamConnection
    {
        return $this->connection;
    }

    public function createChannel($channelName): void
    {
        if (isset($this->channels[$channelName])) {
            throw new \Exception("Channel {$channelName} already exists.");
        }

        $this->channels[$channelName] = $this->connection->channel();
    }

    public function getChannel($channelName): ?AMQPChannel
    {
        return $this->channels[$channelName] ?? null;
    }

    public function publishMessage($channelName, $queue, Request $request)
    {
        $channel = $this->getChannel($channelName);
        if (!$channel) {
            throw new \Exception("Channel {$channelName} does not exist.");
        }

        print_r($queue);
        $channel->queue_declare($queue, false, false, false, false);
        $msg = new AMQPMessage(json_encode([
            'origin' => $request->getOrigin(),
            'destination' => $request->getDestination(),
            'type' => $request->getType(),
            'direction' => 'output',
            'route' => $request->getRoute(),
            'body' => $request->getBody(),
        ]));
        $channel->basic_publish($msg, '', $queue);
    }

    public function consumeMessage($channelName, $queue)
    {
        $channel = $this->getChannel($channelName);
        if (!$channel) {
            throw new \Exception("Channel {$channelName} does not exist.");
        }

        $callback = function ($msg) {
            $messageData = json_decode($msg->body, true);
            $request = new Request(
                $messageData['origin'],
                $messageData['destination'],
                $messageData['body'],
                $messageData['type'],
                $messageData['route']
            );
            $this->router->route($request);
        };

        $channel->queue_declare($queue, false, false, false, false);
        $channel->basic_consume($queue, '', false, true, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    public function sendSyncMessage($channelName, $queue, Request $request)
    {
        $channel = $this->getChannel($channelName) ?? $this->createAndGetChannel($channelName);
        $responseQueue = $channel->queue_declare('', false, false, true, false);

        $correlationId = uniqid();

        $request->setCorrelationId($correlationId);
        $this->publishMessage($channelName, $queue, $request);

        $response = null;
        $callback = function ($msg) use ($correlationId, &$response, $channel) {
            $messageData = json_decode($msg->body, true);
            if ($messageData['correlation_id'] === $correlationId) {
                $response = new Response(
                    $messageData['origin'],
                    $messageData['destination'],
                    $messageData['body'],
                    $messageData['type'],
                    $messageData['route']
                );
                $channel->basic_cancel($msg->delivery_info['consumer_tag']);
            }
        };

        $channel->basic_consume($responseQueue[0], '', false, true, false, false, $callback);

        while ($response === null) {
            $channel->wait();
        }

        return $response;
    }

    protected function createAndGetChannel($channelName)
    {
        $this->createChannel($channelName);
        return $this->getChannel($channelName);
    }

    public function __destruct()
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }
        $this->connection->close();
    }
}
