<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Testcontainers\Container\GenericContainer;

class GenericContainerTest extends ContainerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::$container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();
    }

    public function testExec(): void
    {
        $actual = self::$container->exec(['echo', 'testcontainers']);
        self::assertSame('testcontainers', $actual);
    }
}
