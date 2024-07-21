<?php

return [
    # Client channel
    'channel' => env('AMQP_CHANNEL', env('APP_NAME', 'laravel')),

    # AMQP connection timeout in seconds
    'timeout' => env('AMQP_TIMEOUT', 5),

    # AMQP connection
    'host' => env('RABBITMQ_HOST', 'localhost'),
    'port' => env('RABBITMQ_PORT', 5672),
    'login' => env('RABBITMQ_LOGIN', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
];
