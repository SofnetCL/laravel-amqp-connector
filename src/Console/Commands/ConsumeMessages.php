<?php

namespace Sofnet\AmqpConnector\Console\Commands;

use Illuminate\Console\Command;
use Sofnet\AmqpConnector\Services\AmqpClient;

class ConsumeMessages extends Command
{
    protected $signature = 'amqp:consume:messages';
    protected $description = 'Consume messages from the RabbitMQ queue.';

    protected AmqpClient $amqpClient;

    public function __construct(AmqpClient $amqpClient)
    {
        parent::__construct();
        $this->amqpClient = $amqpClient;
    }

    public function handle()
    {
        $this->info('Starting to consume messages...');
        $this->amqpClient->consumeMessage();
        $this->info('Finished consuming messages.');
    }
}
