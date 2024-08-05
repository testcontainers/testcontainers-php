<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use JsonException;
use RuntimeException;
use Testcontainers\Exception\ContainerNotReadyException;
use Testcontainers\Trait\DockerContainerAwareTrait;

final class WaitForTcpPortOpen implements WaitInterface
{
    use DockerContainerAwareTrait;

    public function __construct(private readonly int $port, private readonly ?string $network = null)
    {
    }

    public static function make(int $port, ?string $network = null): self
    {
        return new self($port, $network);
    }

    /**
     * @throws JsonException
     */
    public function wait(string $id): void
    {
        if (@fsockopen(self::dockerContainerAddress(containerId: $id, networkName: $this->network), $this->port) === false) {
            throw new ContainerNotReadyException($id, new RuntimeException('Unable to connect to container TCP port'));
        }
    }
}
