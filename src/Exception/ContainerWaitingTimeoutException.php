<?php

declare(strict_types=1);

namespace Testcontainers\Exception;

class ContainerWaitingTimeoutException extends \RuntimeException
{
    protected string $containerId;

    public function __construct(string $containerId, ?string $message = null, ?\Throwable $previous = null)
    {
        $this->containerId = $containerId;
        $message ??= sprintf('Timeout reached while waiting for container %s', $containerId);
        parent::__construct($message, 0, $previous);
    }

    public function getContainerId(): string
    {
        return $this->containerId;
    }
}
