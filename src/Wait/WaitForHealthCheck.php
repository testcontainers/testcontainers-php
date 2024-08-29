<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\Docker;
use Docker\DockerClientFactory;
use Http\Client\Socket\Exception\TimeoutException;
use Testcontainers\ContainerRuntime\ContainerRuntimeClient;
use Testcontainers\Exception\ContainerNotReadyException;

class WaitForHealthCheck implements WaitInterface
{
    protected Docker $dockerClient;
    protected int $timeout;
    protected int $pollInterval;

    public function __construct(int $timeout = 5000, int $pollInterval = 1000)
    {
        $this->dockerClient = ContainerRuntimeClient::getDockerClient();
        $this->timeout = $timeout;
        $this->pollInterval = $pollInterval;
    }

    public function wait(string $id): void
    {
        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsedTime = (microtime(true) * 1000) - $startTime;

            if ($elapsedTime > $this->timeout) {
                throw new TimeoutException(sprintf("Health check not healthy after %d ms", $this->timeout));
            }

            $containerInspect = $this->dockerClient->containerInspect($id, [], Docker::FETCH_RESPONSE);
            //$containerStatus = $containerInspect?->getArrayCopy() ?? null;
            var_dump($containerInspect->getBody()->getContents());
            $containerStatus='';
            if ($containerStatus === 'healthy') {
                return;
            }

            if ($containerStatus === 'unhealthy') {
                throw new ContainerNotReadyException(sprintf("Health check failed: %s", $containerStatus));
            }

            usleep($this->pollInterval * 1000);  // Sleep for the polling interval
        }
    }
}
