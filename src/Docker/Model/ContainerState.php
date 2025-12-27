<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class ContainerState
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

    public function getStatus(): ?string
    {
        $status = $this->data['Status'] ?? null;
        return is_string($status) ? $status : null;
    }

    public function getHealth(): ?ContainerHealth
    {
        $health = $this->data['Health'] ?? null;
        if (!is_array($health)) {
            return null;
        }

        return new ContainerHealth($health);
    }
}
