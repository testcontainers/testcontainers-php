<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Wait\WaitStrategy;

interface TestContainer
{
    public function start(): StartedGenericContainer;

    /**
     * TODO: replace with array after deprecated implementation is removed
     * @param array<string, string>|string $env
     */
    public function withEnvironment(array | string $env, ?string $value): static;

    /**
     * @param array<string> $command
     */
    public function withCommand(array $command): static;

    public function withEntrypoint(string $entryPoint): static;

    /**  @param int|string|array<int|string> $ports One or more ports to expose. */
    public function withExposedPorts(...$ports): static;

    public function withWait(WaitStrategy $waitStrategy): static;

    public function withNetwork(string $networkName): static;

    public function withPrivilegedMode(): static;
}
