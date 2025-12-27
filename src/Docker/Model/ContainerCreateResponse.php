<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class ContainerCreateResponse
{
    private ?string $id = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $id = $data['Id'] ?? $data['id'] ?? null;
        $this->id = is_string($id) ? $id : null;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
