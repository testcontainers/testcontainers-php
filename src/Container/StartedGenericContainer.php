<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Docker\API\Client;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\IdResponse;
use Docker\API\Runtime\Client\Client as DockerRuntimeClient;
use Docker\Docker;
use Psr\Http\Message\ResponseInterface;
use Testcontainers\ContainerClient\DockerContainerClient;

class StartedGenericContainer implements StartedTestContainer
{
    protected Docker $dockerClient;

    protected ?string $lastExecId = null;

    public function __construct(protected readonly string $id)
    {
        $this->dockerClient = DockerContainerClient::getDockerClient();
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
            throw new \RuntimeException('Failed to create exec command');
        }

        $this->lastExecId = $exec->getId();

        $contents = $this->dockerClient
            ->execStart($this->lastExecId, null, Client::FETCH_RESPONSE)
            ?->getBody()
            ->getContents() ?? '';

        return preg_replace('/[\x00-\x1F\x7F]/u', '', $contents) ?? '';
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
                DockerRuntimeClient::FETCH_RESPONSE
            )
            ?->getBody()
            ->getContents() ?? '';

        return preg_replace('/[\x00-\x1F\x7F]/u', '', mb_convert_encoding($output, 'UTF-8', 'UTF-8')) ?? '';
    }

    //TODO: replace with the proper implementation
    public function getHost(): string
    {
        return '127.0.0.1';
    }

    //TODO: not ready yet
    public function getMappedPort(int $port): int
    {
        return $this->inspect()->ports[$port];
    }

    /**
     * @throws \JsonException
     */
    public function getFirstMappedPort(): int
    {
        //For some reason, containerInspect can crash when using FETCH_OBJECT option (e.g. with OpenSearch)
        //should be checked within beluga-php/docker-php client library
        /** @var ResponseInterface | null $containerInspectResponse */
        $containerInspectResponse =  $this->dockerClient->containerInspect($this->id, [], Docker::FETCH_RESPONSE);
        if ($containerInspectResponse === null) {
            throw new \RuntimeException('Failed to inspect container');
        }

        $containerInspectResponseAsArray = json_decode(
            $containerInspectResponse->getBody()->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        /** @var array<string, array<array<string, string>>> $ports */
        $ports = $containerInspectResponseAsArray['NetworkSettings']['Ports'] ?? [];

        if ($ports === []) {
            throw new \RuntimeException('Failed to get ports from container');
        }

        $port = array_key_first($ports);

        return (int) $ports[$port][0]['HostPort'];
    }

    public function getName(): string
    {
        // TODO: Implement getName() method.
        return '';
    }

    public function getLabels(): array
    {
        // TODO: Implement getLabels() method.
        return [];
    }


    public function getNetworkNames(): array
    {
        // TODO: Implement getNetworkNames() method.
        return [];
    }

    public function getNetworkId(string $networkName): string
    {
        // TODO: Implement getNetworkId() method.
        return '';
    }

    public function getIpAddress(string $networkName): string
    {
        // TODO: Implement getIpAddress() method.
        return '';
    }
}
