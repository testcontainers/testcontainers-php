<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

interface WaitInterface
{
    public function wait(string $id): void;
}
