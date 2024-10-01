<?php

declare(strict_types=1);

namespace Testcontainers\Utils\PortGenerator;

class RandomUniquePortGenerator implements PortGenerator
{
    /** @var int[] */
    protected static array $assignedPorts = [];

    public function __construct(protected PortGenerator $portGenerator = new RandomPortGenerator())
    {
    }

    public function generatePort(): int
    {
        do {
            $port = $this->portGenerator->generatePort();
        } while (in_array($port, self::$assignedPorts));

        self::$assignedPorts[] = $port;

        return $port;
    }
}
