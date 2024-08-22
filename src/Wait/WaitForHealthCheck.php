<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\Docker;
use Testcontainers\Exception\ContainerNotReadyException;

class WaitForHealthCheck implements WaitInterface
{
    protected Docker $dockerClient;

    public function __construct()
    {
        $this->dockerClient = Docker::create();
    }
    public function wait(string $id): void
    {
        $containerInspect = $this->dockerClient->containerInspect($id);
        $containerInspect->getBody()->getContents();
        dd($containerInspect->getStatusCode());

        if ($status !== 'healthy') {
            throw new ContainerNotReadyException($id);
        }
    }
}
