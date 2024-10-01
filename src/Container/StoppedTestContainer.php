<?php

declare(strict_types=1);

namespace Testcontainers\Container;

interface StoppedTestContainer
{
    public function getId(): string;
}
