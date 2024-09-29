<?php

declare(strict_types=1);

namespace Testcontainers\Exception;

class ContainerStateException extends ContainerException
{
    public function __construct(string $containerId, ?\Throwable $previous = null)
    {
        $message = sprintf('Unable to retrieve state for container %s', $containerId);
        parent::__construct($message, $containerId, $previous);
    }
}
