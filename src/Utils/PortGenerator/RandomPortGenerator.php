<?php

declare(strict_types=1);

namespace Testcontainers\Utils\PortGenerator;

class RandomPortGenerator implements PortGenerator
{
    public function generatePort(): int
    {
        return $this->getRandomPort($this->randomBetweenInclusive(10000, 65535));
    }

    private function randomBetweenInclusive(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    private function getRandomPort(int $port): int
    {
        $connection = @fsockopen("localhost", $port);

        if (is_resource($connection)) {
            fclose($connection);
            throw new \RuntimeException("Port $port is already in use.");
        }

        return $port;
    }
}
