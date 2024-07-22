<?php

namespace Sofnet\AmqpConnector\Console\Commands;

use Illuminate\Console\Command;
use Sofnet\AmqpConnector\Services\Amqp;

class ConsumeRpcMessages extends Command
{
    protected $signature = 'amqp:consume:rpc';
    protected $description = 'Consume messages from the RabbitMQ queue.';

    protected Amqp $amqpClient;

    public function __construct(Amqp $amqpClient)
    {
        parent::__construct();
        $this->amqpClient = $amqpClient;
    }

    public function handle()
    {
        $this->info('Starting to consume rpc messages...');
        $this->amqpClient->connectRpc();
        $this->amqpClient->consumeRpcMessages();
        $this->info('Finished consuming messages.');
    }
}