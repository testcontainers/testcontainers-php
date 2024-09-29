<?php

declare(strict_types=1);

namespace Testcontainers\Exception;

class HealthCheckFailedException extends ContainerNotReadyException
{
    public function __construct(string $containerId, ?\Throwable $previous = null)
    {
        $message = sprintf('Health check failed: Container %s is unhealthy', $containerId);
        parent::__construct($message, $containerId, $previous);
    }
}
