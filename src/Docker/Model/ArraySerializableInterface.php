<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

/**
 * Interface for objects that can be serialized to an array for JSON encoding.
 */
interface ArraySerializableInterface
{
    /**
     * Converts the object to an array suitable for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
