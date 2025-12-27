<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class PortBinding
{
    private ?string $hostPort = null;
    private ?string $hostIp = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $hostPort = $data['HostPort'] ?? $data['hostPort'] ?? null;
        $this->hostPort = is_string($hostPort) ? $hostPort : null;

        $hostIp = $data['HostIp'] ?? $data['hostIp'] ?? null;
        $this->hostIp = is_string($hostIp) ? $hostIp : null;
    }

    public function setHostPort(string $hostPort): self
    {
        $this->hostPort = $hostPort;

        return $this;
    }

    public function setHostIp(string $hostIp): self
    {
        $this->hostIp = $hostIp;

        return $this;
    }

    public function getHostPort(): ?string
    {
        return $this->hostPort;
    }

    public function getHostIp(): ?string
    {
        return $this->hostIp;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $payload = [];
        if ($this->hostPort !== null) {
            $payload['HostPort'] = $this->hostPort;
        }
        if ($this->hostIp !== null) {
            $payload['HostIp'] = $this->hostIp;
        }

        return $payload;
    }
}
