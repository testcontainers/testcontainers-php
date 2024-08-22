<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\Docker;
use Symfony\Component\Process\Process;
use Testcontainers\Exception\ContainerNotReadyException;

class WaitForLog implements WaitInterface
{
    protected Docker $dockerClient;

    public function __construct(private string $message, private bool $enableRegex = false)
    {
        $this->dockerClient = Docker::create();
    }

    public function wait(string $id): void
    {
        $logs = $this->dockerClient->containerLogs($id);

        $output = $logs->getBody()->getContents();

        if ($this->enableRegex) {
            if (!preg_match($this->message, $output)) {
                throw new ContainerNotReadyException($id, new \RuntimeException('Message not found in logs'));
            }
        } else {
            if (!str_contains($output, $this->message)) {
                throw new ContainerNotReadyException($id, new \RuntimeException('Message not found in logs'));
            }
        }
    }
}
