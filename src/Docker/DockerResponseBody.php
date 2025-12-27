<?php

declare(strict_types=1);

namespace Testcontainers\Docker;

class DockerResponseBody
{
    public function __construct(private string $contents)
    {
    }

    public function getContents(): string
    {
        return $this->contents;
    }
}
