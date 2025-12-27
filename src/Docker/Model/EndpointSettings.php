<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class EndpointSettings
{
    private ?string $networkID = null;
    private ?string $ipAddress = null;

    /** @var array<int, string>|null */
    private ?array $aliases = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $networkID = $data['networkID'] ?? $data['NetworkID'] ?? null;
        $this->networkID = is_string($networkID) ? $networkID : null;

        $ipAddress = $data['IPAddress'] ?? null;
        $this->ipAddress = is_string($ipAddress) ? $ipAddress : null;

        if (isset($data['aliases']) && is_array($data['aliases'])) {
            $this->aliases = $data['aliases'];
        } elseif (isset($data['Aliases']) && is_array($data['Aliases'])) {
            $this->aliases = $data['Aliases'];
        }
    }

    public function getNetworkID(): ?string
    {
        return $this->networkID;
    }

    public function getIPAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * @return array<int, string>|null
     */
    public function getAliases(): ?array
    {
        return $this->aliases;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];
        if ($this->networkID !== null) {
            $payload['NetworkID'] = $this->networkID;
        }
        if ($this->aliases !== null) {
            $payload['Aliases'] = $this->aliases;
        }

        return $payload;
    }
}
