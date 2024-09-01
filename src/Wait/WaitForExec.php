<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Closure;
use Docker\API\Client;
use Docker\API\Model\ContainersIdExecPostBody;
use Testcontainers\Exception\ContainerWaitingTimeoutException;

/**
 * Uses $timout and $pollInterval in milliseconds to set the parameters for waiting.
 */
class WaitForExec extends BaseWait
{
    protected ContainersIdExecPostBody $execConfig;

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

    public function wait(string $id): void
    {
        $this->execConfig = (new ContainersIdExecPostBody())
            ->setCmd($this->command)
            ->setAttachStdout(true)
            ->setAttachStderr(true);

        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsedTime = (microtime(true) * 1000) - $startTime;

            if ($elapsedTime > $this->timeout) {
                throw new ContainerWaitingTimeoutException($id);
            }

            // Create and start the exec command
            $exec = $this->dockerClient->containerExec($id, $this->execConfig);
            $contents = $this->dockerClient
                ->execStart($exec->getId(), null, Client::FETCH_RESPONSE)
                ?->getBody()
                ->getContents() ?? '';

            // Inspect the exec to check the exit code
            $execInspect = $this->dockerClient->execInspect($exec->getId());
            $exitCode = $execInspect->getExitCode();

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
