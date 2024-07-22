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
    protected $queueChannel;

    /** @var AMQPChannel */
    protected $rpcChannel;

    /** @var AMQPStreamConnection */
    protected $connection;

    /** @var Router */
    protected $router;

    /** @var string */
    protected $queueName;

    /** @var string */
    protected $rpcQueueName;

    /** @var string */
    protected $dlxQueueName;

    /** @var int */
    protected int $timeout = 10;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function connectQueue($host, $port, $login, $password, $queueName): void
    {
        if (!$this->connection) {
            $this->connection = new AMQPStreamConnection($host, $port, $login, $password);
        }
        $this->queueName = $queueName;
        $this->queueChannel = $this->connection->channel();
        $this->queueChannel->queue_declare($this->queueName, false, true, false, false);
    }

    public function connectRpc($host, $port, $login, $password, $rpcQueueName): void
    {
        if (!$this->connection) {
            $this->connection = new AMQPStreamConnection($host, $port, $login, $password);
        }
        $this->rpcQueueName = $rpcQueueName;
        $this->dlxQueueName = $rpcQueueName . '.dlx';
        $this->rpcChannel = $this->connection->channel();

        // Declarar la cola DLX
        $this->rpcChannel->queue_declare($this->dlxQueueName, false, true, false, false);

        // Declarar la cola RPC con DLX
        $arguments = [
            'x-dead-letter-exchange' => ['S', ''],
            'x-dead-letter-routing-key' => ['S', $this->dlxQueueName],
        ];
        $this->rpcChannel->queue_declare($this->rpcQueueName, false, true, false, false, $arguments);
    }

    public function setConnection(AMQPStreamConnection $connection): void
    {
        $this->connection = $connection;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getConnection(): AMQPStreamConnection
    {
        return $this->connection;
    }

    public function getQueueChannel(): ?AMQPChannel
    {
        return $this->queueChannel;
    }

    public function getRpcChannel(): ?AMQPChannel
    {
        return $this->rpcChannel;
    }

    public function dispatchQueueMessage($queue, Request $request)
    {
        $channel = $this->getQueueChannel();
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

    public function consumeQueueMessages()
    {
        $queueChannel = $this->getQueueChannel();
        if (!$queueChannel) {
            throw new \Exception("Queue channel does not exist.");
        }

        $callback = function ($msg) use ($queueChannel) {
            try {
                $messageData = json_decode($msg->body, true);
                $request = new Request(
                    $messageData['origin'],
                    $messageData['destination'],
                    $messageData['body'],
                    $messageData['type'],
                    $messageData['route']
                );
                $this->router->route($request);

                // Acknowledge the message only if it was successfully processed
                $queueChannel->basic_ack($msg->delivery_info['delivery_tag']);
            } catch (\Exception $e) {
                // Handle or log the exception
                error_log("Error processing normal queue message: " . $e->getMessage());
                $queueChannel->basic_nack($msg->delivery_info['delivery_tag'], false, true); // Requeue the message
            }
        };

        $queueChannel->basic_consume($this->queueName, '', false, true, false, false, $callback);

        try {
            while ($queueChannel->is_consuming()) {
                $queueChannel->wait(null, false);
            }
        } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
            error_log("AMQPProtocolChannelException: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error during normal message consumption: " . $e->getMessage());
        }
    }

    public function consumeRpcMessages()
    {
        $rpcChannel = $this->getRpcChannel();
        if (!$rpcChannel) {
            throw new \Exception("RPC channel does not exist.");
        }

        $callback = function ($msg) use ($rpcChannel) {
            try {
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

                // Verifica que haya una cola de respuesta (reply_to)
                $replyToQueue = $msg->get('reply_to');
                if (!$replyToQueue) {
                    throw new \Exception("No reply_to queue specified for RPC response.");
                }

                // Crear un nuevo canal para la respuesta
                $responseChannel = $this->connection->channel();

                // Publica la respuesta en la cola de respuesta RPC
                $responseMessage = new AMQPMessage(
                    json_encode([
                        'origin' => $response->getOrigin(),
                        'destination' => $response->getDestination(),
                        'body' => $response->getBody(),
                        'type' => $response->getType(),
                        'route' => $response->getRoute(),
                    ]),
                    ['correlation_id' => $msg->get('correlation_id')]
                );

                $responseChannel->basic_publish($responseMessage, '', $replyToQueue);

                // Cerrar el canal de respuesta
                $responseChannel->close();

                // Acknowledge the message only if it was successfully processed
                $rpcChannel->basic_ack($msg->delivery_info['delivery_tag']);
            } catch (\Exception $e) {
                // Handle or log the exception
                error_log("Error processing RPC queue message: " . $e->getMessage());
                if ($rpcChannel->is_open()) {
                    $rpcChannel->basic_nack($msg->delivery_info['delivery_tag'], false, true); // Requeue the message
                }
            }
        };

        $rpcChannel->basic_consume($this->rpcQueueName, '', false, false, false, false, $callback);

        try {
            while ($rpcChannel->is_consuming()) {
                $rpcChannel->wait(null, false);
            }
        } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
            error_log("AMQPProtocolChannelException: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error during RPC message consumption: " . $e->getMessage());
        }
    }

    public function sendSyncMessage($queue, Request $request)
    {
        $channel = $this->connection->channel();
        list($responseQueue,,) = $channel->queue_declare('', false, false, true, false);

        $correlationId = uniqid();

        $request->setCorrelationId($correlationId);
        $request->setReplyTo($responseQueue);

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

        $response = null;
        $callback = function ($msg) use ($correlationId, &$response, $channel) {
            try {
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
            } catch (\Exception $e) {
                // Handle or log the exception
                error_log("Error processing sync message response: " . $e->getMessage());
            }
        };

        $consumerTag = $channel->basic_consume($responseQueue, '', false, true, false, false, $callback);

        $timeout = $this->timeout;
        $start = time();
        while ($response === null && (time() - $start) < $timeout) {
            $channel->wait(null, false);
        }

        $channel->basic_cancel($consumerTag);
        $channel->close();

        if ($response === null) {
            throw new \Exception("No response received within the timeout period.");
        }

        return $response;
    }

    public function __destruct()
    {
        if ($this->queueChannel && $this->queueChannel->is_open()) {
            $this->queueChannel->close();
        }

        if ($this->rpcChannel && $this->rpcChannel->is_open()) {
            $this->rpcChannel->close();
        }

        // Cerrar la conexión
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
