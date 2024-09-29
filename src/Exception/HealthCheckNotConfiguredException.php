<?php

declare(strict_types=1);

namespace Testcontainers\Exception;

class HealthCheckNotConfiguredException extends ContainerNotReadyException
{
    public function __construct(string $containerId, ?\Throwable $previous = null)
    {
        $message = sprintf('Health check not configured for container %s', $containerId);
        parent::__construct($message, $containerId, $previous);
    }
}
