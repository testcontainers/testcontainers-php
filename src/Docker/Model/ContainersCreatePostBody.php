<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class ContainersCreatePostBody
{
    private ?string $image = null;

    /** @var array<int, string> */
    private array $cmd = [];

    /** @var array<string, string>|null */
    private ?array $labels = null;

    private ?string $hostname = null;
    private ?string $workingDir = null;
    private ?string $user = null;

    /** @var array<int, string> */
    private array $env = [];

    /** @var array<string, object>|null */
    private ?array $exposedPorts = null;

    private ?HostConfig $hostConfig = null;

    /** @var array<int, string>|null */
    private ?array $entrypoint = null;

    private ?HealthConfig $healthcheck = null;
    private ?NetworkingConfig $networkingConfig = null;

    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @param array<int, string> $cmd
     */
    public function setCmd(array $cmd): self
    {
        $this->cmd = $cmd;

        return $this;
    }

    /**
     * @param array<string, string>|null $labels
     */
    public function setLabels(?array $labels): self
    {
        $this->labels = $labels;

        return $this;
    }

    public function setHostname(?string $hostname): self
    {
        $this->hostname = $hostname;

        return $this;
    }

    public function setWorkingDir(?string $workingDir): self
    {
        $this->workingDir = $workingDir;

        return $this;
    }

    public function setUser(?string $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param array<int, string> $env
     */
    public function setEnv(array $env): self
    {
        $this->env = $env;

        return $this;
    }

    /**
     * @param array<string, object> $exposedPorts
     */
    public function setExposedPorts(array $exposedPorts): self
    {
        $this->exposedPorts = $exposedPorts;

        return $this;
    }

    public function setHostConfig(?HostConfig $hostConfig): self
    {
        $this->hostConfig = $hostConfig;

        return $this;
    }

    /**
     * @param array<int, string> $entrypoint
     */
    public function setEntrypoint(array $entrypoint): self
    {
        $this->entrypoint = $entrypoint;

        return $this;
    }

    public function setHealthcheck(HealthConfig $healthcheck): self
    {
        $this->healthcheck = $healthcheck;

        return $this;
    }

    public function setNetworkingConfig(NetworkingConfig $networkingConfig): self
    {
        $this->networkingConfig = $networkingConfig;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];

        if ($this->image !== null) {
            $payload['Image'] = $this->image;
        }
        if ($this->cmd !== []) {
            $payload['Cmd'] = $this->cmd;
        }
        if ($this->labels !== null) {
            $payload['Labels'] = $this->labels;
        }
        if ($this->hostname !== null) {
            $payload['Hostname'] = $this->hostname;
        }
        if ($this->workingDir !== null) {
            $payload['WorkingDir'] = $this->workingDir;
        }
        if ($this->user !== null) {
            $payload['User'] = $this->user;
        }
        if ($this->env !== []) {
            $payload['Env'] = $this->env;
        }
        if ($this->exposedPorts !== null) {
            $payload['ExposedPorts'] = $this->exposedPorts;
        }
        if ($this->hostConfig !== null) {
            $payload['HostConfig'] = $this->hostConfig->toArray();
        }
        if ($this->entrypoint !== null) {
            $payload['Entrypoint'] = $this->entrypoint;
        }
        if ($this->healthcheck !== null) {
            $payload['Healthcheck'] = $this->healthcheck->toArray();
        }
        if ($this->networkingConfig !== null) {
            $payload['NetworkingConfig'] = $this->networkingConfig->toArray();
        }

        return $payload;
    }
}
