<?php

declare(strict_types=1);

namespace Testcontainers\Modules;

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForLog;

class RedisContainer extends GenericContainer
{
    public function __construct(string $version = 'latest')
    {
        parent::__construct('redis:' . $version);
        $this->withExposedPorts(6379);
        $this->withWait(new WaitForLog('Ready to accept connections'));
    }
}
