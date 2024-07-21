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
    protected $queueName;

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
        $this->queueName = $mainQueue;
        $this->rpcQueueName = $rpcQueue;
        $this->declareChannels();
    }

    public function setConnection(AMQPStreamConnection $connection, $mainQueue, $rpcQueue): void
    {
        $this->connection = $connection;
        $this->queueName = $mainQueue;
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

        $this->mainChannel->queue_declare($this->queueName, false, true, false, false);

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
        ]), [
            'correlation_id' => $request->getCorrelationId(),
            'reply_to' => $request->getReplyTo(),
        ]);

        $channel->basic_publish($msg, '', $queue);
    }


    public function consumeMessages()
    {
        $channel = $this->getMainChannel();
        if (!$channel) {
            throw new \Exception("Main channel does not exist.");
        }

        $normalQueueCallback = function ($msg) {
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

        $rpcQueueCallback = function ($msg) use ($channel) {
            $messageData = json_decode($msg->body, true);
            $request = new Request(
                $messageData['origin'],
                $messageData['destination'],
                $messageData['body'],
                $messageData['type'],
                $messageData['route']
            );

            // Procesa el request y genera una respuesta
            $response = $this->router->route($request);

            // Publica la respuesta en la cola de respuesta RPC
            $responseMessage = new AMQPMessage(json_encode([
                'origin' => $response->getOrigin(),
                'destination' => $response->getDestination(),
                'body' => $response->getBody(),
                'type' => $response->getType(),
                'route' => $response->getRoute(),
                'correlation_id' => $request->getCorrelationId(),
            ]), [
                'correlation_id' => $request->getCorrelationId(),
            ]);

            $replyToQueue = $msg->get('reply_to');
            if ($replyToQueue) {
                $channel->basic_publish($responseMessage, '', $replyToQueue);
            }

            $channel->basic_ack($msg->delivery_info['delivery_tag']);
        };

        // Registra el callback para la cola normal
        $channel->basic_consume($this->queueName, '', false, true, false, false, $normalQueueCallback);

        // Registra el callback para la cola RPC
        $channel->basic_consume($this->rpcQueueName, '', false, true, false, false, $rpcQueueCallback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    public function sendSyncMessage($queue, Request $request)
    {
        $channel = $this->connection->channel();
        list($responseQueue,,) = $channel->queue_declare('', false, false, true, false);

        $correlationId = uniqid();

        $request->setCorrelationId($correlationId);
        $request->setReplyTo($responseQueue);
        $this->publishMessage($queue, $request);

        $response = null;
        $callback = function ($msg) use ($correlationId, &$response, $channel) {
            $messageData = json_decode($msg->body, true);
            if (isset($messageData['correlation_id']) && $messageData['correlation_id'] === $correlationId) {
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

        $channel->basic_consume($responseQueue, '', false, true, false, false, $callback);

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
