<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Registry;

class RedisContainerTest extends TestCase
{
//    public static function tearDownAfterClass(): void
//    {
//        parent::tearDownAfterClass();
//
//        Registry::cleanup();
//    }

    public function testRedisContainer(): void
    {
        $redisContainer = (new GenericContainer('redis:alpine'))
            ->withExposedPorts(6379)
            ->start();

        $redisClient = new \Predis\Client([
            'host' => 'localhost',
            'port' => 6379,
        ]);

        $redisClient->set('greetings', 'Hello, World!');

        $this->assertEquals('Hello, World!', $redisClient->get('greetings'));
        $redisContainer->remove();
    }
}
