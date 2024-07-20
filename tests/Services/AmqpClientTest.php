<?php

namespace Tests\Unit;

use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PHPUnit\Framework\TestCase;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Sofnet\AmqpConnector\Request;
use Sofnet\AmqpConnector\Services\AmqpClient;
use Sofnet\AmqpConnector\Routing\Router;

class AmqpClientTest extends TestCase
{
    protected $client;
    protected $router;
    protected $channel;
    protected $connection;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Router
        $this->router = Mockery::mock(Router::class);

        // Mock Connection
        $this->connection = Mockery::mock(AMQPStreamConnection::class);

        // Mock Channel
        $this->channel = Mockery::mock(\PhpAmqpLib\Channel\AMQPChannel::class);

        // Mock Connection methods
        $this->connection->shouldReceive('channel')->andReturn($this->channel);

        // Create client with mocked Router
        $appQueue = 'app_name';
        $this->client = new AmqpClient($this->router, $appQueue);
        $this->client->setConnection($this->connection);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAccessToConnection2()
    {
        $connectionMock = Mockery::mock(AMQPStreamConnection::class);
        $channelMock = Mockery::mock(AMQPChannel::class);

        // Expect that the 'channel' method will be called and it will return our channel mock
        $connectionMock->shouldReceive('channel')->once()->andReturn($channelMock);

        // Expect that the 'queue_declare' method will be called on the channel mock
        $channelMock->shouldReceive('queue_declare')->once()->andReturnNull();

        // Expect that the 'close' method will be called on the channel mock
        $channelMock->shouldReceive('close')->once()->andReturnNull();

        $router = new Router();
        $amqpClient = new AmqpClient($router, 'test_queue');
        $amqpClient->setConnection($connectionMock);

        // Call the method to be tested
        $amqpClient->createChannel('test_channel');
        $amqpClient->publishMessage('test_channel', 'test_queue', Mockery::mock(Request::class));

        // Assertions can be added here if needed
        $this->assertInstanceOf(AMQPStreamConnection::class, $amqpClient->getConnection());
    }

    public function testAccessToConnection()
    {
        $this->assertInstanceOf(AMQPStreamConnection::class, $this->client->getConnection());
        $this->assertEquals($this->connection, $this->client->getConnection());
    }

    public function testCreateChannel()
    {
        $channels = [
            'test_channel',
            'another_channel',
            'third_channel'
        ];

        foreach ($channels as $channel) {
            $this->client->createChannel($channel);
            $this->assertEquals($this->channel, $this->client->getChannel($channel));
        }
    }

    public function testDuplicateChannel()
    {
        $this->client->createChannel('test_channel');
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Channel test_channel already exists.');
        $this->client->createChannel('test_channel');
    }
}
