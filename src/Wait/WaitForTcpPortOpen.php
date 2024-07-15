<?php

declare(strict_types=1);

namespace Testcontainer\Wait;

use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Testcontainer\Exception\ContainerNotReadyException;

/**
 * @phpstan-import-type ContainerInspect from \Testcontainer\Container\Container
 */
final class WaitForTcpPortOpen implements WaitInterface
{
    public function __construct(private readonly int $port)
    {
    }

    public static function make(int $port): self
    {
        return new self($port);
    }

    /**
     * @throws JsonException
     */
    public function wait(string $id): void
    {
        if (@fsockopen($this->findContainerAddress($id), $this->port) === false) {
            throw new ContainerNotReadyException($id, new RuntimeException('Unable to connect to container TCP port'));
        }
    }

    /**
     * @throws JsonException
     */
    private function findContainerAddress(string $id): string
    {
        $process = new Process(['docker', 'inspect', $id]);
        $process->mustRun();

        /** @var ContainerInspect $data */
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $containerAddress = $data[0]['NetworkSettings']['IPAddress'] ?? null;

        if (! is_string($containerAddress)) {
            throw new ContainerNotReadyException($id, new RuntimeException('Unable to find container IP address'));
        }

        return $containerAddress;
    }
}
