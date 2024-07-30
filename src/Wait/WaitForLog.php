<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Symfony\Component\Process\Process;
use Testcontainers\Exception\ContainerNotReadyException;

class WaitForLog implements WaitInterface
{
    public function __construct(private string $message, private bool $enableRegex = false)
    {
    }

    public function wait(string $id): void
    {
        $process = new Process(['docker', 'logs', $id]);
        $process->mustRun();

        $output = $process->getOutput() . PHP_EOL . $process->getErrorOutput();

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
