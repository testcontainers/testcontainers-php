<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\API\Model\ContainersIdJsonGetResponse200;
use Docker\Docker;
use Testcontainers\ContainerRuntime\ContainerRuntimeClient;
use Testcontainers\Exception\ContainerNotReadyException;

/**
 * Simply makes container inspect and checks if container is running.
 * Uses $timout and $pollInterval in milliseconds to set the parameters for waiting.
 */
class WaitForContainerRunning implements WaitInterface
{
    protected Docker $dockerClient;

    public function __construct(protected int $timeout = 10000, protected int $pollInterval = 500)
    {
        $this->dockerClient = ContainerRuntimeClient::getDockerClient();
    }

    public function wait(string $id): void
    {
        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsedTime = (microtime(true) * 1000) - $startTime;

            if ($elapsedTime > $this->timeout) {
                throw new ContainerNotReadyException($id);
            }

            /** @var ContainersIdJsonGetResponse200 | null $containerInspect */
            $containerInspect = $this->dockerClient->containerInspect($id);
            $containerStatus = $containerInspect?->getState()?->getStatus();

            if ($containerStatus === 'running') {
                return;
            }

            var_dump($containerStatus);

            usleep($this->pollInterval * 1000);
        }
    }
}
