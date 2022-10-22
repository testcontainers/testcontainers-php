<?php

declare(strict_types=1);

namespace Testcontainer\Wait;

class WaitForNothing implements WaitInterface
{
    public function wait(string $id): void
    {
        // does nothing
    }
}
