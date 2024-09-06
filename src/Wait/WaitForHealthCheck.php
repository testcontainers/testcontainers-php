<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\Docker;
use Http\Client\Socket\Exception\TimeoutException;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Exception\ContainerNotReadyException;

class WaitForHealthCheck extends BaseWaitStrategy
{
    public function __construct(protected int $timeout = 5000, protected int $pollInterval = 1000)
    {
        parent::__construct($timeout, $pollInterval);
    }

    public function wait(StartedTestContainer $container): void
    {
        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsedTime = (microtime(true) * 1000) - $startTime;

            if ($elapsedTime > $this->timeout) {
                throw new TimeoutException(sprintf("Health check not healthy after %d ms", $this->timeout));
            }

            /** @var \Psr\Http\Message\ResponseInterface | null $containerInspect */
            $containerInspect = $container->getClient()->containerInspect($container->getId(), [], Docker::FETCH_RESPONSE);
            //$containerStatus = $containerInspect?->getArrayCopy() ?? null;
            $containerStatus = '';
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
