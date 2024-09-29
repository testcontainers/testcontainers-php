<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Utils\PortGenerator\FixedPortGenerator;

/**
 * Added for backward compatibility.
 * @deprecated Use GenericContainer instead.
 * TODO: Remove in next major release.
 */
class Container extends GenericContainer
{
    protected ?StartedTestContainer $startedContainer = null;

    protected ?StoppedTestContainer $stoppedContainer = null;

    public static function make(string $image): self
    {
        return new self($image);
    }

    /**
     * @deprecated Use `withCommand` instead
     * @param array<string> $cmd
     */
    public function withCmd(array $cmd): self
    {
        return $this->withCommand($cmd);
    }

    /**
     * @deprecated Use `withEntrypoint` instead
     * TODO: this is just dummy method for compatibility,
     * the implementation with Docker Engine API should be discussed
     */
    public function withHostname(string $hostname): self
    {
        return $this;
    }

    /**
     * @deprecated Use `withPrivilegedMode` instead
     */
    public function withPrivileged(bool $privileged = true): self
    {
        return $this->withPrivilegedMode($privileged);
    }

    /**
     * @deprecated Use `withExposedPorts` instead
     */
    public function withPort(string $localPort, string $containerPort): self
    {
        $this->withPortGenerator(new FixedPortGenerator([(int)$localPort]));
        return $this->withExposedPorts($containerPort);
    }

    /**
     * @deprecated there will be no replacement
     */
    public function withImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @deprecated Use `start` instead
     */
    public function run(): self
    {
        $this->startedContainer = $this->start();

        return $this;
    }

    /**
     * @param array<string> $commandAsArray
     * @deprecated Use 'exec' from StartedTestContainer instead
     */
    public function execute(array $commandAsArray): string
    {
        if ($this->startedContainer === null) {
            throw new \RuntimeException('Container is not started');
        }

        return $this->startedContainer->exec($commandAsArray);
    }

    /**
     * @deprecated Use 'logs' from StartedTestContainer instead
     */
    public function logs(): string
    {
        if ($this->startedContainer === null) {
            throw new \RuntimeException('Container is not started');
        }

        return $this->startedContainer->logs();
    }

    /**
     * @deprecated Use 'getHost' from StartedTestContainer instead
     */
    public function getAddress(): string
    {
        if ($this->startedContainer === null) {
            throw new \RuntimeException('Container is not started');
        }

        return $this->startedContainer->getHost();
    }

    /**
     * @deprecated Use 'getFirstMappedPort' from StartedTestContainer instead
     */
    public function getPort(): int
    {
        if ($this->startedContainer === null) {
            throw new \RuntimeException('Container is not started');
        }

        return $this->startedContainer->getFirstMappedPort();
    }

    /**
     * @deprecated Use 'stop' from StartedTestContainer instead
     */
    public function kill(): self
    {
        $this->dockerClient->containerKill($this->id);

        return $this;
    }

    /**
     * @deprecated Use `stop` from StartedTestContainer instead
     */
    public function stop(): self
    {
        if ($this->startedContainer === null) {
            throw new \RuntimeException('Container is not started');
        }

        $this->stoppedContainer = $this->startedContainer->stop();

        return $this;
    }

    /**
     * @deprecated Use 'restart' method from StartedTestContainer instead
     */
    public function restart(): self
    {
        if ($this->startedContainer === null) {
            throw new \RuntimeException('Container is not started');
        }

        $restartedTestContainer = $this->startedContainer->restart();
        $this->startedContainer = $restartedTestContainer;

        return $this;
    }

    /**
     * @deprecated Use 'stop' method from StartedTestContainer instead
     */
    public function remove(): self
    {
        if ($this->startedContainer === null) {
            throw new \RuntimeException('Container is not started');
        }

        $this->startedContainer->stop();

        return $this;
    }
}
