<?php

namespace Sofnet\AmqpConnector\Facades;

use Illuminate\Support\Facades\Facade;
use Sofnet\AmqpConnector\Request;
use Sofnet\AmqpConnector\Response;
use Sofnet\AmqpConnector\Services\AmqpClient;

/**
 * @method static void dispatch(string $channel, string $route, $body)
 * @method static \Sofnet\AmqpConnector\Response get(string $channel, string $route, $body)
 */
class AmqpConsumer extends Facade
{
    protected static function getFacadeAccessor()
    {
        return AmqpClient::class;
    }

    public static function dispatch(string $channel, string $route, $body): void
    {
        $client = static::getFacadeRoot();
        $request = new Request(
            env('APP_NAME'),
            $channel,
            $body,
            Request::ASYNC,
            $route
        );
        $client->publishMessage($channel, $request);
    }

    public static function get(string $channel, string $route, $body): Response
    {
        $client = static::getFacadeRoot();
        $request = new Request(
            env('APP_NAME'),
            $channel,
            $body,
            Request::SYNC,
            $route
        );
        return $client->sendSyncMessage($channel, $route, $request);
    }
}
