<?php

declare(strict_types=1);

namespace Testcontainer\Wait;

interface WaitInterface
{
    public function wait(string $id): void;
}
