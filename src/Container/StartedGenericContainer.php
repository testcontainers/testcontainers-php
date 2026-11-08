<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Docker\API\Client;
use Docker\API\Exception\ContainerExecConflictException;
use Docker\API\Exception\ContainerLogsNotFoundException;
use Docker\API\Exception\NotFoundException;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\ContainersIdJsonGetResponse200;
use Docker\API\Model\EndpointSettings;
use Docker\API\Model\ExecIdStartPostBody;
use Docker\API\Model\IdResponse;
use Docker\API\Model\PortBinding;
use Docker\API\Runtime\Client\Client as DockerRuntimeClient;
use Docker\Docker;
use Docker\Stream\DockerRawStream;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Exception\ContainerNotRunningException;
use Testcontainers\Utils\HostResolver;

class StartedGenericContainer implements StartedTestContainer
{
    protected Docker $dockerClient;

    protected ?ContainersIdJsonGetResponse200 $inspectResponse = null;

    protected ?string $lastExecId = null;

    public function __construct(protected readonly string $id, ?Docker $dockerClient = null)
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

    public function getClient(): Docker
    {
        return $this->dockerClient;
    }

    /**
     * @param list<string> $command
     * @throws ContainerNotRunningException If the container has stopped or is paused.
     */
    public function exec(array $command): string
    {
        $execConfig = (new ContainersIdExecPostBody())
            ->setCmd($command)
            ->setAttachStdout(true)
            ->setAttachStderr(true);

        // Create and start the exec command
        try {
            /** @var IdResponse | null $exec */
            $exec = $this->dockerClient->containerExec($this->id, $execConfig);
        } catch (ContainerExecConflictException $e) {
            // docker-php reports every conflict as "container is paused"; Docker's own message says what is wrong
            throw new ContainerNotRunningException($e->getErrorResponse()->getMessage() ?? $e->getMessage(), $this->id, $e);
        }

        if ($exec === null || $exec->getId() === null) {
            throw new RuntimeException('Failed to create exec command');
        }

        $this->lastExecId = $exec->getId();

        $startConfig = new ExecIdStartPostBody();
        $startConfig->setDetach(false);

        $response = $this->dockerClient->execStart($this->lastExecId, $startConfig, Client::FETCH_RESPONSE);
        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException("Failed to start exec command: {$response->getBody()}");
        }

        // The exec is created without a TTY, so its output is always multiplexed
        return $this->readOutput($response, true);
    }

    public function stop(): StoppedTestContainer
    {
        try {
            $autoRemove = $this->inspect()?->getHostConfig()?->getAutoRemove() ?? false;
            $this->dockerClient->containerStop($this->id);
            if (!$autoRemove) {
                $this->dockerClient->containerDelete($this->id);
            }
        } catch (NotFoundException) {
            // The container is already gone, e.g. removed by Docker after its process exited
        }

        return new StoppedGenericContainer($this->id);
    }

    public function restart(): self
    {
        $this->dockerClient->containerRestart($this->id);

        return $this;
    }

    public function logs(): string
    {
        $response = $this->dockerClient->containerLogs(
            $this->id,
            ['stdout' => true, 'stderr' => true],
            DockerRuntimeClient::FETCH_RESPONSE
        );
        if ($response->getStatusCode() === 404) {
            throw new ContainerLogsNotFoundException($response);
        }
        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException("Failed to get container logs: {$response->getBody()}");
        }

        // Logs of a container with a TTY are sent as is, otherwise they are multiplexed
        $hasTty = $this->inspect()?->getConfig()?->getTty() ?? false;

        return $this->readOutput($response, !$hasTty);
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
        if ($port === null) {
            throw new RuntimeException('Failed to get first mapped port for container');
        }
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
         * For the loop $this->inspect() shouldn't be cached.
         */
        usleep(300 * 1000);
        $ports = $this->inspect()?->getNetworkSettings()?->getPorts();

        if ($ports === null) {
            throw new RuntimeException('Failed to get ports from container');
        }

        return $ports;
    }

    /**
     * Read stdout and stderr, in the order they were written, from a Docker stream response.
     * A multiplexed stream wraps each chunk of output in a frame with an 8-byte header, which is stripped here.
     * Trailing line breaks are trimmed, so the output of a single-line command has none.
     */
    private function readOutput(ResponseInterface $response, bool $multiplexed): string
    {
        $output = '';
        $append = static function (string $chunk) use (&$output): void {
            $output .= $chunk;
        };

        $stream = new DockerRawStream($response->getBody(), $multiplexed);
        $stream->onStdout($append);
        $stream->onStderr($append);
        $stream->wait();

        return rtrim($output, "\r\n");
    }

    /**
     * @deprecated No longer used by exec() and logs(), which now decode the Docker stream with readOutput().
     */
    protected function sanitizeOutput(string $output): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $output) ?? '';
    }
}
