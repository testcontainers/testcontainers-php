<?php

declare(strict_types=1);

namespace Testcontainer\Wait;

use JsonException;
use RuntimeException;
use Testcontainer\Exception\ContainerNotReadyException;
use Testcontainer\Trait\DockerContainerAwareTrait;

final class WaitForTcpPortOpen implements WaitInterface
{
    use DockerContainerAwareTrait;

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
        if (@fsockopen($this->getContainerAddress($id), $this->port) === false) {
            throw new ContainerNotReadyException($id, new RuntimeException('Unable to connect to container TCP port'));
        }
    }
}
