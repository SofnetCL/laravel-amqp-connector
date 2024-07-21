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

    /** @var string */
    protected $rpcQueueName;

    /** @var int */
    protected int $timeout = 10;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function connect($host, $port, $login, $password, $mainQueue, $rpcQueue): void
    {
        $this->connection = new AMQPStreamConnection($host, $port, $login, $password);
        $this->mainQueueName = $mainQueue;
        $this->rpcQueueName = $rpcQueue;
        $this->declareChannels();
    }

    public function setConnection(AMQPStreamConnection $connection, $mainQueue, $rpcQueue): void
    {
        $this->connection = $connection;
        $this->mainQueueName = $mainQueue;
        $this->rpcQueueName = $rpcQueue;
        $this->declareChannels();
    }

    public function getConnection(): AMQPStreamConnection
    {
        return $this->connection;
    }

    protected function declareChannels(): void
    {
        if ($this->mainChannel) {
            throw new \Exception("Main channel already exists.");
        }

        $this->mainChannel = $this->connection->channel();

        // Declare the main queue
        $this->mainChannel->queue_declare($this->mainQueueName, false, true, false, false);

        // Declare the RPC queue with DLX configuration
        $arguments = [
            'x-dead-letter-exchange' => ['S', ''], // No exchange, direct to DLX queue
            'x-dead-letter-routing-key' => ['S', ''] // Default routing key for DLX
        ];

        $this->mainChannel->queue_declare($this->rpcQueueName, false, true, false, false, false, $arguments);
    }

    public function getMainChannel(): ?AMQPChannel
    {
        return $this->mainChannel;
    }

    public function publishMessage($queue, Request $request)
    {
        $channel = $this->getMainChannel();
        if (!$channel) {
            throw new \Exception("Main channel does not exist.");
        }

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
        $channel = $this->connection->channel();
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

        try {
            while ($response === null) {
                $channel->wait(null, false, $this->timeout);
            }
        } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
            $channel->close();
            throw new \Exception('Timeout waiting for response');
        }

        $channel->close();
        return $response;
    }

    public function __destruct()
    {
        if ($this->mainChannel) {
            $this->mainChannel->close();
        }

        if ($this->connection) {
            $this->connection->close();
        }
    }
}
