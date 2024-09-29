<?php

declare(strict_types=1);

namespace Testcontainers\Exception;

class ContainerException extends \RuntimeException
{
    protected string $containerId;

    public function __construct(string $message, string $containerId = '', ?\Throwable $previous = null)
    {
        $this->containerId = $containerId;
        parent::__construct($message, 0, $previous);
    }

    public function getContainerId(): string
    {
        return $this->containerId;
    }
}
