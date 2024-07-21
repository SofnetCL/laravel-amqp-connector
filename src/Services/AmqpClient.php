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
    /** @var AMQPChannel */
    protected $mainChannel;

    /** @var AMQPStreamConnection */
    protected $connection;

    /** @var Router */
    protected $router;

    /** @var string */
    protected $mainQueueName;

    public function __construct(Router $router, $mainQueueName)
    {
        $this->router = $router;
        $this->mainQueueName = $mainQueueName;
    }

    public function connect($host, $port, $login, $password): void
    {
        $this->connection = new AMQPStreamConnection($host, $port, $login, $password);
        $this->createMainChannel();
    }

    public function setConnection(AMQPStreamConnection $connection): void
    {
        $this->connection = $connection;
        $this->createMainChannel();
    }

    public function getConnection(): AMQPStreamConnection
    {
        return $this->connection;
    }

    protected function createMainChannel(): void
    {
        if ($this->mainChannel) {
            throw new \Exception("Main channel already exists.");
        }

        $this->mainChannel = $this->connection->channel();
        $this->mainChannel->queue_declare($this->mainQueueName, false, true, false, false);
    }

    public function getMainChannel(): ?AMQPChannel
    {
        return $this->mainChannel;
    }

    public function publishMessage($queue, Request $request)
    {
        $channel = $this->connection->channel();  // Create a new channel for publishing
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
        $channel->close();
    }

    public function consumeMessage()
    {
        $channel = $this->getMainChannel();
        if (!$channel) {
            throw new \Exception("Main channel does not exist.");
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

        $channel->basic_consume($this->mainQueueName, '', false, true, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    public function sendSyncMessage($queue, Request $request)
    {
        $channel = $this->connection->channel();  // Create a new channel for sending synchronous messages
        $responseQueue = $channel->queue_declare('', false, false, true, false);

        $correlationId = uniqid();

        $request->setCorrelationId($correlationId);
        $this->publishMessage($queue, $request);

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

        $channel->close();
        return $response;
    }

    public function __destruct()
    {
        if ($this->mainChannel) {
            $this->mainChannel->close();
        }
        $this->connection->close();
    }
}
