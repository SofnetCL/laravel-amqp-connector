<?php

namespace Sofnet\AmqpConnector\Console\Commands;

use Illuminate\Console\Command;
use Sofnet\AmqpConnector\Services\Amqp;

class ConsumeQueueMessages extends Command
{
    protected $signature = 'amqp:consume:queue';
    protected $description = 'Consume messages from the RabbitMQ queue.';

    protected Amqp $amqpClient;

    public function __construct(Amqp $amqpClient)
    {
        parent::__construct();
        $this->amqpClient = $amqpClient;
    }

    public function handle()
    {
        $this->info('Starting to consume queue messages...');
        $this->amqpClient->connectQueue();
        $this->amqpClient->consumeQueueMessages();
        $this->info('Finished consuming messages.');
    }
}
