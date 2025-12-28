<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class ContainersIdJsonGetResponse200
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function getName(): ?string
    {
        $name = $this->data['Name'] ?? null;
        return is_string($name) ? $name : null;
    }

    public function getConfig(): ?ContainerConfig
    {
        $config = $this->data['Config'] ?? null;
        if (!is_array($config)) {
            return null;
        }

        return new ContainerConfig($config);
    }

    public function getHostConfig(): ?HostConfig
    {
        $hostConfig = $this->data['HostConfig'] ?? null;
        if (!is_array($hostConfig)) {
            return null;
        }

        return new HostConfig($hostConfig);
    }

    public function getNetworkSettings(): ?NetworkSettings
    {
        $networkSettings = $this->data['NetworkSettings'] ?? null;
        if (!is_array($networkSettings)) {
            return null;
        }

        return new NetworkSettings($networkSettings);
    }

    public function getState(): ?ContainerState
    {
        $state = $this->data['State'] ?? null;
        if (!is_array($state)) {
            return null;
        }

        return new ContainerState($state);
    }
}
