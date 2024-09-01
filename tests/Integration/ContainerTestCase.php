<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Testcontainers\Container\GenericContainer;

abstract class ContainerTestCase extends TestCase
{
    protected static GenericContainer $container;

    protected function tearDown(): void
    {
        self::$container->remove();
    }
}
