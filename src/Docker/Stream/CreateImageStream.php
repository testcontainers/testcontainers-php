<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Stream;

class CreateImageStream
{
    public function __construct(private string $payload)
    {
    }

    public function wait(): void
    {
        // The request already consumes the stream. This is a no-op to keep API parity.
    }

    public function getPayload(): string
    {
        return $this->payload;
    }
}
