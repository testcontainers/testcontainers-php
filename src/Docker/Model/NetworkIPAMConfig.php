<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class NetworkIPAMConfig
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

    public function getGateway(): ?string
    {
        $gateway = $this->data['Gateway'] ?? null;
        return is_string($gateway) ? $gateway : null;
    }
}
