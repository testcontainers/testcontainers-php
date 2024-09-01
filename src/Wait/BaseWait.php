<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\Docker;
use Testcontainers\ContainerRuntime\ContainerRuntimeClient;

abstract class BaseWait implements WaitInterface
{
    protected Docker $dockerClient;

    public function __construct(protected int $timeout = 10000, protected int $pollInterval = 500)
    {
        $this->dockerClient = ContainerRuntimeClient::getDockerClient();
    }

    abstract public function wait(string $id): void;
}
