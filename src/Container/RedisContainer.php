<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Wait\WaitForLog;

class RedisContainer extends GenericContainer
{
    private function __construct(string $version)
    {
        parent::__construct('redis:' . $version);
        $this->withWait(new WaitForLog('Ready to accept connections'));
    }

    public static function make(string $version = 'latest'): self
    {
        return new self($version);
    }
}
