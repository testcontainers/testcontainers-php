<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Docker\DockerClient;
use Testcontainers\Docker\Model\ContainersIdExecPostBody;
use Testcontainers\Docker\Model\ContainersIdJsonGetResponse200;
use Testcontainers\Docker\Model\EndpointSettings;
use Testcontainers\Docker\Model\IdResponse;
use Testcontainers\Docker\Model\PortBinding;
use RuntimeException;
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Utils\HostResolver;

class StartedGenericContainer implements StartedTestContainer
{
    protected DockerClient $dockerClient;

    protected ?ContainersIdJsonGetResponse200 $inspectResponse = null;

    protected ?string $lastExecId = null;

    public function __construct(protected readonly string $id, ?DockerClient $dockerClient = null)
    {
        $this->dockerClient = $dockerClient ?? DockerContainerClient::getDockerClient();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLastExecId(): ?string
    {
        return $this->lastExecId;
    }

    public function getClient(): DockerClient
    {
        return $this->dockerClient;
    }

    /**
     * @param list<string> $command
     */
    public function exec(array $command): string
    {
        $execConfig = (new ContainersIdExecPostBody())
            ->setCmd($command)
            ->setAttachStdout(true)
            ->setAttachStderr(true);

        // Create and start the exec command
        /** @var IdResponse | null $exec */
        $exec = $this->dockerClient->containerExec($this->id, $execConfig);

        if ($exec === null || $exec->getId() === null) {
            throw new RuntimeException('Failed to create exec command');
        }

        $this->lastExecId = $exec->getId();

        $contents = $this->dockerClient
            ->execStart($this->lastExecId, null, DockerClient::FETCH_RESPONSE)
            ?->getBody()
            ->getContents() ?? '';

        return $this->sanitizeOutput($contents);
    }

    public function stop(): StoppedTestContainer
    {
        $this->dockerClient->containerStop($this->id);
        $this->dockerClient->containerDelete($this->id);

        return new StoppedGenericContainer($this->id);
    }

    public function restart(): self
    {
        $this->dockerClient->containerRestart($this->id);

        return $this;
    }

    public function logs(): string
    {
        $output = $this->dockerClient
            ->containerLogs(
                $this->id,
                ['stdout' => true, 'stderr' => true],
                DockerClient::FETCH_RESPONSE
            )
            ?->getBody()
            ->getContents() ?? '';

        /**
         * @var string|false $converted
         */
        $converted = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
        return $this->sanitizeOutput($converted == false ? $output : $converted);
    }

    public function getHost(): string
    {
        return (new HostResolver($this->dockerClient))->resolveHost();
    }

    public function getMappedPort(int $port): int
    {
        $ports = (array) $this->getBoundPorts();
        /** @var PortBinding | null $portBinding */
        $portBinding = $ports["{$port}/tcp"][0] ?? null;
        $mappedPort = $portBinding?->getHostPort();
        if ($mappedPort !== null) {
            return (int) $mappedPort;
        }

        throw new RuntimeException("Failed to get mapped port ‘{$mappedPort}’ for container");
    }

    public function getFirstMappedPort(): int
    {
        $ports = (array) $this->getBoundPorts();
        $port = array_key_first($ports);
        /** @var PortBinding | null  $firstPortBinding */
        $firstPortBinding = $ports[$port][0] ?? null;
        $firstMappedPort = $firstPortBinding?->getHostPort();
        if ($firstMappedPort !== null) {
            return (int) $firstMappedPort;
        }

        throw new RuntimeException('Failed to get first mapped port for container');
    }

    public function getName(): string
    {
        return trim($this->inspect()?->getName() ?? '', '/ ');
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return (array) $this->inspect()?->getConfig()?->getLabels();
    }

    /**
     * @return string[]
     */
    public function getNetworkNames(): array
    {
        $networks = (array) $this->inspect()?->getNetworkSettings()?->getNetworks();
        return array_keys($networks);
    }

    public function getNetworkId(string $networkName): string
    {
        $networks = (array) $this->inspect()?->getNetworkSettings()?->getNetworks();
        /** @var EndpointSettings | null $endpointSettings */
        $endpointSettings = $networks[$networkName] ?? null;
        $networkID = $endpointSettings?->getNetworkID();
        if ($networkID !== null) {
            return $networkID;
        }

        throw new RuntimeException("Network with name ‘{$networkName}’ does not exist");
    }

    public function getIpAddress(string $networkName): string
    {
        $networks = (array) $this->inspect()?->getNetworkSettings()?->getNetworks();
        /** @var EndpointSettings | null $endpointSettings */
        $endpointSettings = $networks[$networkName] ?? null;
        $ipAddress = $endpointSettings?->getIPAddress();
        if ($ipAddress !== null) {
            return $ipAddress;
        }

        throw new RuntimeException("Network with name ‘{$networkName}’ does not exist");
    }

    protected function inspect(): ContainersIdJsonGetResponse200 | null
    {
        if ($this->inspectResponse === null) {
            /** @var ContainersIdJsonGetResponse200 | null $inspectResponse */
            $inspectResponse = $this->dockerClient->containerInspect($this->id);
            $this->inspectResponse = $inspectResponse;
        }

        return $this->inspectResponse;
    }

    /**
     * @return iterable<string, array<PortBinding>>
     * @throws RuntimeException
     */
    public function getBoundPorts(): iterable
    {
        /**
         * For some reason, in the latest Docker releases, at this moment, the container might not be fully started.
         * This can lead to issues when trying to retrieve the ports.
         * TODO: find a better strategy to ensure the container is fully started or run in a loop until it is ready.
         */
        usleep(300 * 1000);
        /** @var ContainersIdJsonGetResponse200 | null $inspectResponse */
        $inspectResponse = $this->dockerClient->containerInspect($this->id);
        $this->inspectResponse = $inspectResponse;
        $ports = $inspectResponse?->getNetworkSettings()?->getPorts();

        if ($ports === null) {
            throw new RuntimeException('Failed to get ports from container');
        }

        return $ports;
    }

    protected function sanitizeOutput(string $output): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $output) ?? '';
    }
}
