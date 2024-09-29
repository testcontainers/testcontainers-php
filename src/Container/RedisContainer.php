<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Utils\PortGenerator\FixedPortGenerator;
use Testcontainers\Wait\WaitForLog;

/**
 * Left for namespace backward compatibility
 * @deprecated Use \Testcontainers\Modules\RedisContainer instead.
 * TODO: Remove in next major release.
 */
class RedisContainer extends Container
{
    public function __construct(string $version = 'latest')
    {
        parent::__construct('redis:' . $version);
        $this->withPortGenerator(new FixedPortGenerator([6379]));
        $this->withExposedPorts(6379);
        $this->withWait(new WaitForLog('Ready to accept connections'));
    }

    public static function make(string $version = 'latest'): self
    {
        return new self($version);
    }
}
