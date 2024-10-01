<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\API\Model\ContainersIdJsonGetResponse200;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Exception\ContainerStateException;
use Testcontainers\Exception\ContainerWaitingTimeoutException;
use Testcontainers\Exception\HealthCheckFailedException;
use Testcontainers\Exception\HealthCheckNotConfiguredException;
use Testcontainers\Exception\UnknownHealthStatusException;

/**
 * Wait strategy that waits until the container's health status is 'healthy'.
 *
 * Possible health statuses:
 * - "none":      No health check configured.
 * - "starting":  Health check is in progress.
 * - "healthy":   Container is healthy.
 * - "unhealthy": Container is unhealthy.
 */
class WaitForHealthCheck extends BaseWaitStrategy
{
    public function wait(StartedTestContainer $container): void
    {
        $startTime = microtime(true);

        while (true) {
            $elapsedTime = (microtime(true) - $startTime) * 1000;

            if ($elapsedTime > $this->timeout) {
                throw new ContainerWaitingTimeoutException($container->getId());
            }

            /** @var ContainersIdJsonGetResponse200|null $containerInspect */
            $containerInspect = $container->getClient()->containerInspect($container->getId());

            $containerState = $containerInspect?->getState();

            if ($containerState !== null) {
                $health = $containerState->getHealth();

                if ($health !== null) {
                    $status = $health->getStatus();

                    switch ($status) {
                        case 'healthy':
                            return; // Container is healthy
                        case 'starting':
                            // Health check is still in progress; continue waiting
                            break;
                        case 'unhealthy':
                            throw new HealthCheckFailedException($container->getId());
                        case 'none':
                            throw new HealthCheckNotConfiguredException($container->getId());
                        default:
                            throw new UnknownHealthStatusException($container->getId(), (string)$status);
                    }
                } else {
                    // Health is null; treat as 'none' status
                    throw new HealthCheckNotConfiguredException($container->getId());
                }
            } else {
                // Container state is null
                throw new ContainerStateException($container->getId());
            }

            usleep($this->pollInterval * 1000);
        }
    }
}
