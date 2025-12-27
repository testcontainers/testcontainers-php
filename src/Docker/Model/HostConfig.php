<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class HostConfig
{
    /** @var array<string, array<int, PortBinding>>|null */
    private ?array $portBindings = null;
    private ?bool $privileged = null;

    /** @var array<int, Mount>|null */
    private ?array $mounts = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        if (array_key_exists('Privileged', $data)) {
            $this->privileged = (bool) $data['Privileged'];
        }
        if (isset($data['PortBindings']) && is_array($data['PortBindings'])) {
            $this->portBindings = $this->hydratePortBindings($data['PortBindings']);
        }
        if (isset($data['Mounts']) && is_array($data['Mounts'])) {
            $this->mounts = array_map(
                static fn (array $mount) => new Mount($mount),
                $data['Mounts']
            );
        }
    }

    /**
     * @param array<string, array<int, PortBinding>> $portBindings
     */
    public function setPortBindings(array $portBindings): self
    {
        $this->portBindings = $portBindings;

        return $this;
    }

    public function setPrivileged(bool $privileged): self
    {
        $this->privileged = $privileged;

        return $this;
    }

    /**
     * @param array<int, Mount> $mounts
     */
    public function setMounts(array $mounts): self
    {
        $this->mounts = $mounts;

        return $this;
    }

    public function getPrivileged(): ?bool
    {
        return $this->privileged;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];

        if ($this->portBindings !== null) {
            $payload['PortBindings'] = [];
            foreach ($this->portBindings as $port => $bindings) {
                $payload['PortBindings'][$port] = array_map(
                    static fn (PortBinding $binding) => $binding->toArray(),
                    $bindings
                );
            }
        }

        if ($this->privileged !== null) {
            $payload['Privileged'] = $this->privileged;
        }

        if ($this->mounts !== null) {
            $payload['Mounts'] = array_map(
                static fn (Mount $mount) => $mount->toArray(),
                $this->mounts
            );
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $bindings
     * @return array<string, array<int, PortBinding>>
     */
    private function hydratePortBindings(array $bindings): array
    {
        $result = [];
        foreach ($bindings as $port => $items) {
            if (!is_array($items)) {
                $result[$port] = [];
                continue;
            }
            $result[$port] = array_values(array_filter(array_map(
                static function ($item): ?PortBinding {
                    if (!is_array($item)) {
                        return null;
                    }

                    return new PortBinding($item);
                },
                $items
            )));
        }

        return $result;
    }
}
