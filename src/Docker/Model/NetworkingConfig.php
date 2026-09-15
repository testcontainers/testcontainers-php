<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class NetworkingConfig
{
    /** @var array<string, EndpointSettings> */
    private array $endpointsConfig = [];

    /**
     * @param array<string, EndpointSettings> $endpointsConfig
     */
    public function setEndpointsConfig(array $endpointsConfig): self
    {
        $this->endpointsConfig = $endpointsConfig;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];
        if ($this->endpointsConfig !== []) {
            $payload['EndpointsConfig'] = [];
            foreach ($this->endpointsConfig as $name => $settings) {
                $payload['EndpointsConfig'][$name] = $settings->toArray();
            }
        }

        return $payload;
    }
}
