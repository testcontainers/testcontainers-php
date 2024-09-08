<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Testcontainers\Container\StartedTestContainer;

abstract class BaseWaitStrategy implements WaitStrategy
{
    public function __construct(protected int $timeout = 10000, protected int $pollInterval = 500)
    {
    }

    abstract public function wait(StartedTestContainer $container): void;
}
