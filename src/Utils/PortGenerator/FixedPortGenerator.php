<?php

declare(strict_types=1);

namespace Testcontainers\Utils\PortGenerator;

class FixedPortGenerator implements PortGenerator
{
    protected int $portIndex = 0;

    public function __construct(
        /** @var int[] */
        protected array $ports
    ) {
    }

    public function generatePort(): int
    {
        if (!isset($this->ports[$this->portIndex])) {
            throw new \RuntimeException('No more ports available in the fixed list.');
        }

        return $this->ports[$this->portIndex++];
    }
}
