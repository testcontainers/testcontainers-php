<?php

declare(strict_types=1);

namespace Testcontainers\Container;

class StoppedGenericContainer implements StoppedTestContainer
{
    public function __construct(protected readonly string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
