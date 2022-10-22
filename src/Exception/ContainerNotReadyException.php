<?php

declare(strict_types=1);

namespace Testcontainer\Exception;

class ContainerNotReadyException extends \RuntimeException
{
    public function __construct(string $id, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Container %s is not ready', $id), 0, $previous);
    }
}
