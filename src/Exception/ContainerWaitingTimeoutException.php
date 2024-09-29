<?php

declare(strict_types=1);

namespace Testcontainers\Exception;

class ContainerWaitingTimeoutException extends ContainerNotReadyException
{
    public function __construct(string $containerId, ?string $message = null, ?\Throwable $previous = null)
    {
        $message ??= sprintf('Timeout reached while waiting for container %s', $containerId);
        parent::__construct($message, $containerId, $previous);
    }
}
