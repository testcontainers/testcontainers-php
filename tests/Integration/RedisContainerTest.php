<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Predis\Client;
use Testcontainers\Modules\RedisContainer;

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
            'host' => self::$container->getHost(),
            'port' => self::$container->getFirstMappedPort(),
        ]);

        $redisClient->ping();

        $this->assertTrue($redisClient->isConnected());

        $redisClient->set('greetings', 'Hello, World!');

        $this->assertEquals('Hello, World!', $redisClient->get('greetings'));
    }
}
