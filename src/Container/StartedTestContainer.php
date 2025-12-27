<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Docker\DockerClient;
use Testcontainers\Docker\Model\PortBinding;

interface StartedTestContainer
{
    /**
     * @param list<string> $command
     */
    public function exec(array $command): string;

    /**
     * @return iterable<string, array<PortBinding>>
     */
    public function getBoundPorts(): iterable;

    public function getClient(): DockerClient;

    public function getFirstMappedPort(): int;

    public function getHost(): string;

    public function getId(): string;

    public function getIpAddress(string $networkName): string;

    /**
     * @return array<string, string>
     */
    public function getLabels(): array;

    public function logs(): string;

    public function getLastExecId(): string | null;

    public function getMappedPort(int $port): int;

    public function getName(): string;

    public function getNetworkId(string $networkName): string;

    /**
     * @return string[]
     */
    public function getNetworkNames(): array;

    public function restart(): self;

    public function stop(): StoppedTestContainer;
}
