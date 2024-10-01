<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Closure;
use Docker\API\Model\ExecIdJsonGetResponse200;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Exception\ContainerWaitingTimeoutException;

/**
 * Uses $timout and $pollInterval in milliseconds to set the parameters for waiting.
 */
class WaitForExec extends BaseWaitStrategy
{
    /**
     * @param array<string> $command
     */
    public function __construct(
        protected array $command,
        protected ?Closure $checkFunction = null,
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

            $contents = $container->exec($this->command);

            // Inspect the exec to check the exit code
            /** @var ExecIdJsonGetResponse200 | null $execInspect */
            $execInspect = $container->getClient()->execInspect($container->getLastExecId() ?? '');
            $exitCode = $execInspect?->getExitCode();

            // If a custom check function is provided, use it to validate the command output
            if ($this->checkFunction !== null) {
                $checkResult = ($this->checkFunction)($exitCode, $contents);
                if ($checkResult) {
                    return;
                }
            } elseif ($exitCode === 0) {
                return;  // Command succeeded
            }

            usleep($this->pollInterval * 1000);
        }
    }
}
