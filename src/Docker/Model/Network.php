<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class Network
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

    public function getIPAM(): ?NetworkIPAM
    {
        $ipam = $this->data['IPAM'] ?? null;
        if (!is_array($ipam)) {
            return null;
        }

        return new NetworkIPAM($ipam);
    }
}
