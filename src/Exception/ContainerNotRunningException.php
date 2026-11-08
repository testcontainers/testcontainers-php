<?php

declare(strict_types=1);

namespace Testcontainers\Exception;

/**
 * Thrown when an operation needs a running container, but the container has stopped or is paused.
 */
class ContainerNotRunningException extends ContainerException
{
}
