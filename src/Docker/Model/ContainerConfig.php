<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class ContainerConfig
{
    /** @var array<mixed> */
    private array $data;

    /**
     * @param array<mixed> $data
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
        if (!is_array($labels)) {
            return null;
        }

        $result = [];
        foreach ($labels as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
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
        return is_array($entrypoint) ? array_values(array_filter($entrypoint, 'is_string')) : null;
    }
}
