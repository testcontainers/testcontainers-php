<?php

declare(strict_types=1);

namespace Testcontainers\Exception;

class UnknownHealthStatusException extends ContainerNotReadyException
{
    public function __construct(string $containerId, string $status, ?\Throwable $previous = null)
    {
        $message = sprintf('Unknown health status %s for container %s', $status, $containerId);
        parent::__construct($message, $containerId, $previous);
    }
}
