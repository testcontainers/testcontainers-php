<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use RuntimeException;
use Symfony\Component\Process\Process;
use Testcontainers\Exception\ContainerNotReadyException;

class WaitForHealthCheck implements WaitInterface
{
    public function wait(string $id): void
    {
        $process = new Process(['docker', 'inspect', '--format', '{{json .State.Health.Status}}', $id]);
        $process->mustRun();

        $status = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_string($status)) {
            throw new ContainerNotReadyException($id, new RuntimeException('Invalid json output'));
        }

        $status = trim($status, '"');

        if ($status !== 'healthy') {
            throw new ContainerNotReadyException($id);
        }
    }
}
