<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Docker\Docker;
use JsonException;
use RuntimeException;
use Testcontainers\Exception\ContainerNotReadyException;

//TODO: not ready yet
final class WaitForTcpPortOpen implements WaitStrategy
{
    private Docker $dockerClient;

    public function __construct(private readonly int $port, private readonly ?string $network = null)
    {
        $this->dockerClient = Docker::create();
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
        $containerInspectResult = $this->dockerClient->containerInspect($id);
        $dockerContainerNetworks = $containerInspectResult->getNetworkSettings()->getNetworks();
        $dockerContainerAddress = '';
        foreach ($dockerContainerNetworks as $network) {
            if ($network->getNetworkID() === $this->network) {
                $dockerContainerAddress = $network->getIPAddress();
                break;
            }
        }
        if (@fsockopen($dockerContainerAddress, $this->port) === false) {
            throw new ContainerNotReadyException($id, new RuntimeException('Unable to connect to container TCP port'));
        }
    }
}
