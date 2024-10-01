<?php

declare(strict_types=1);

namespace Testcontainers\Container;

/**
 * The IP protocols supported by Docker.
 */
enum InternetProtocol: string
{
    case TCP = 'TCP';
    case UDP = 'UDP';

    public function toDockerNotation(): string
    {
        return strtolower($this->value);
    }

    public static function fromDockerNotation(string $protocol): self
    {
        return self::from(strtoupper($protocol));
    }
}
