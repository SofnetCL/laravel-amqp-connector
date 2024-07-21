<?php

namespace Sofnet\AmqpConnector\Facades;

use Illuminate\Support\Facades\Facade;
use Sofnet\AmqpConnector\Request;
use Sofnet\AmqpConnector\Response;
use Sofnet\AmqpConnector\Services\Amqp;

/**
 * @method static void dispatch(string $channel, string $route, $body)
 * @method static \Sofnet\AmqpConnector\Response get(string $channel, string $route, $body)
 */
class AmqpConsumer extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Amqp::class;
    }

    public static function dispatch(string $channel, string $route, $body): void
    {
        /** @var Amqp $client */
        $client = static::getFacadeRoot();
        $request = new Request(
            env('APP_NAME'),
            $channel,
            $body,
            Request::ASYNC,
            $route
        );

        $client->connect();

        $client->publishMessage($channel, $request);
    }

    public static function get(string $channel, string $route, $body): Response
    {
        /** @var Amqp $client */
        $client = static::getFacadeRoot();
        $request = new Request(
            env('APP_NAME'),
            $channel,
            $body,
            Request::SYNC,
            $route
        );

        $client->connect();

        return $client->sendSyncMessage($channel,  $request);
    }
}
