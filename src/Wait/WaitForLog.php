<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Exception\ContainerWaitingTimeoutException;

/**
 * Uses $timout and $pollInterval in milliseconds to set the parameters for waiting.
 */
class WaitForLog extends BaseWaitStrategy
{
    public function __construct(
        protected string $message,
        protected bool $enableRegex = false,
        int $timeout = 10000,
        int $pollInterval = 500
    ) {
        parent::__construct($timeout, $pollInterval);
    }

    public function wait(StartedTestContainer $container): void
    {
        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsedTime = (microtime(true) * 1000) - $startTime;

            if ($elapsedTime > $this->timeout) {
                throw new ContainerWaitingTimeoutException($container->getId());
            }

            $output = $container->logs();

            if ($this->enableRegex) {
                if (preg_match($this->message, $output)) {
                    return;
                }
            } elseif (str_contains($output, $this->message)) {
                return;
            }

            usleep($this->pollInterval * 1000);
        }
    }
}
