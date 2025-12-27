<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class NetworkSettings
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
     * @return array<string, array<int, PortBinding>>|null
     */
    public function getPorts(): ?array
    {
        if (!array_key_exists('Ports', $this->data)) {
            return null;
        }

        $ports = $this->data['Ports'];
        if ($ports === null) {
            return null;
        }
        if (!is_array($ports)) {
            return null;
        }

        $result = [];
        foreach ($ports as $port => $bindings) {
            if (!is_array($bindings)) {
                $result[$port] = [];
                continue;
            }
            $result[$port] = array_values(array_filter(array_map(
                static function ($binding): ?PortBinding {
                    if (!is_array($binding)) {
                        return null;
                    }

                    return new PortBinding($binding);
                },
                $bindings
            )));
        }

        return $result;
    }

    /**
     * @return array<string, EndpointSettings>|null
     */
    public function getNetworks(): ?array
    {
        $networks = $this->data['Networks'] ?? null;
        if (!is_array($networks)) {
            return null;
        }

        $result = [];
        foreach ($networks as $name => $settings) {
            if (!is_array($settings)) {
                continue;
            }
            $result[$name] = new EndpointSettings($settings);
        }

        return $result;
    }
}
