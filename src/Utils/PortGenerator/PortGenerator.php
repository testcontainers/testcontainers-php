<?php

declare(strict_types=1);

namespace Testcontainers\Utils\PortGenerator;

interface PortGenerator
{
    public function generatePort(): int;
}
