<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class ContainerConfig
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

    /**
     * @return array<string, string>|null
     */
    public function getLabels(): ?array
    {
        $labels = $this->data['Labels'] ?? null;
        return is_array($labels) ? $labels : null;
    }

    public function getHealthcheck(): ?HealthConfig
    {
        $health = $this->data['Healthcheck'] ?? null;
        if (!is_array($health)) {
            return null;
        }

        return new HealthConfig($health);
    }

    /**
     * @return array<int, string>|null
     */
    public function getEntrypoint(): ?array
    {
        $entrypoint = $this->data['Entrypoint'] ?? null;
        return is_array($entrypoint) ? $entrypoint : null;
    }
}
