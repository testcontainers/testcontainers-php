<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Wait\WaitForLog;

class RedisContainer extends GenericContainer
{
    public function __construct(string $version = 'latest')
    {
        parent::__construct('redis:' . $version);
        $this->withExposedPorts(6379);
        $this->withWait(new WaitForLog('Ready to accept connections'));
    }

    /**
     *  @deprecated Use constructor instead
     *  Left for backward compatibility
     */
    public static function make(string $version = 'latest'): self
    {
        return new self($version);
    }
}
