<?php

declare(strict_types=1);

namespace Testcontainers\Docker;

use Testcontainers\Docker\Model\ContainerCreateResponse;
use Testcontainers\Docker\Model\ContainersCreatePostBody;
use Testcontainers\Docker\Model\ContainersIdExecPostBody;
use Testcontainers\Docker\Model\ContainersIdJsonGetResponse200;
use Testcontainers\Docker\Model\ExecIdJsonGetResponse200;
use Testcontainers\Docker\Model\IdResponse;
use Testcontainers\Docker\Model\Network;
use Testcontainers\Docker\Stream\CreateImageStream;

/**
 * Container runtime operations used by Testcontainers.
 *
 * Implementations talk to the runtime in different ways, e.g. the Docker
 * Engine HTTP API over a socket ({@see DockerClient}) or a command line
 * binary such as `docker` or `podman` ({@see Cli\CliDockerClient}).
 */
interface DockerClientInterface
{
    public const FETCH_RESPONSE = 1;

    /**
     * @param array<string, mixed> $query
     */
    public function containerCreate(ContainersCreatePostBody $body, array $query = []): ?ContainerCreateResponse;

    public function containerStart(string $id): void;

    public function containerStop(string $id): void;

    public function containerDelete(string $id): void;

    public function containerRestart(string $id): void;

    public function containerExec(string $id, ContainersIdExecPostBody $body): ?IdResponse;

    /**
     * @param array<string, mixed>|null $config
     */
    public function execStart(string $id, ?array $config = null, int $fetch = self::FETCH_RESPONSE): ?DockerResponse;

    public function execInspect(string $id): ?ExecIdJsonGetResponse200;

    /**
     * @param array<string, mixed> $params
     */
    public function containerLogs(string $id, array $params = [], int $fetch = self::FETCH_RESPONSE): ?DockerResponse;

    public function containerInspect(string $id): ?ContainersIdJsonGetResponse200;

    /**
     * @param resource $handle
     * @param array<string, mixed> $query
     */
    public function putContainerArchive(string $id, $handle, array $query = [], int $fetch = self::FETCH_RESPONSE): ?DockerResponse;

    /**
     * @param array<string, mixed> $query
     * @param list<string> $headers
     */
    public function imageCreate(?string $name, array $query = [], array $headers = []): CreateImageStream;

    public function networkInspect(string $name): ?Network;
}
