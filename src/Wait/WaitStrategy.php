<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Testcontainers\Container\StartedTestContainer;

interface WaitStrategy
{
    public function wait(StartedTestContainer $container): void;
}
