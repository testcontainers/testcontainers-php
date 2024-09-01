<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Predis\Client;
use Testcontainers\Container\RedisContainer;

class RedisContainerTest extends ContainerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::$container = (new RedisContainer())
            ->start();
    }

    public function testRedisContainer(): void
    {
        $redisClient = new Client([
            'host' => 'localhost',
            'port' => 6379,
        ]);

        $redisClient->ping();

        $this->assertTrue($redisClient->isConnected());

        $redisClient->set('greetings', 'Hello, World!');

        $this->assertEquals('Hello, World!', $redisClient->get('greetings'));
    }
}
