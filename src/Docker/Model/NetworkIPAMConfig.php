<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class NetworkIPAMConfig
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

    public function getGateway(): ?string
    {
        $gateway = $this->data['Gateway'] ?? null;
        return is_string($gateway) ? $gateway : null;
    }
}
