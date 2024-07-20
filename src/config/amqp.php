<?php

return [
    # Client channel
    'channel' => env('AMQP_APP_QUEUE', env('APP_NAME', 'laravel')),

    # AMQP connection
    'host' => env('RABBITMQ_HOST', 'localhost'),
    'port' => env('RABBITMQ_PORT', 5672),
    'login' => env('RABBITMQ_LOGIN', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
];
