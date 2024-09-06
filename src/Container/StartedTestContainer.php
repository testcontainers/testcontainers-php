<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Docker\Docker;

interface StartedTestContainer
{
    public function stop(): StoppedTestContainer;

    public function restart(): self;

    public function getClient(): Docker;

    public function getHost(): string;

    public function getFirstMappedPort(): int;

    public function getMappedPort(int $port): int;

    public function getName(): string;

    public function getLabels(): array;

    public function getId(): string;

    public function getLastExecId(): string | null;

    public function getNetworkNames(): array;

    public function getNetworkId(string $networkName): string;

    public function getIpAddress(string $networkName): string;

    /**
     * @param list<string> $command
     */
    public function exec(array $command): string;

    public function logs(): string;
}
