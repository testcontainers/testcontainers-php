<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\API\Runtime\Client\Client;
use Testcontainers\Exception\ContainerWaitingTimeoutException;

/**
 * Uses $timout and $pollInterval in milliseconds to set the parameters for waiting.
 */
class WaitForLog extends BaseWait
{
    public function __construct(
        protected string $message,
        protected bool $enableRegex = false,
        int $timeout = 10000,
        int $pollInterval = 500
    ) {
        parent::__construct($timeout, $pollInterval);
    }

    public function wait(string $id): void
    {
        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsedTime = (microtime(true) * 1000) - $startTime;

            if ($elapsedTime > $this->timeout) {
                throw new ContainerWaitingTimeoutException($id);
            }

            $output = $this->dockerClient
                ->containerLogs($id, ['stdout' => true, 'stderr' => true], Client::FETCH_RESPONSE)
                ?->getBody()
                ->getContents() ?? '';

            $output = preg_replace('/[\x00-\x1F\x7F]/u', '', mb_convert_encoding($output, 'UTF-8', 'UTF-8')) ?? '';

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
