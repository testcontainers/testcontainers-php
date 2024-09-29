<?php

declare(strict_types=1);

namespace Testcontainers\Utils;

use Testcontainers\Container\InternetProtocol;

class PortNormalizer
{
    /**
     * Normalize a port specification to ensure it includes a protocol.
     * Defaults to 'tcp' if no protocol is specified.
     *
     * @param string|int $port Port to normalize.
     * @return string Normalized port string.
     */
    public static function normalizePort(string|int $port, InternetProtocol $internetProtocol = InternetProtocol::TCP): string
    {
        if (is_int($port)) {
            // Direct integer ports default to tcp
            return "{$port}/{$internetProtocol->toDockerNotation()}";
        }

        // Check if the port specification already includes a protocol
        if (is_string($port) && !str_contains($port, '/')) {
            return "{$port}/{$internetProtocol->toDockerNotation()}";
        }

        return $port;
    }
}
