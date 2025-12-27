<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

/**
 * Trait providing helper methods for building serialized arrays.
 */
trait ArraySerializableTrait
{
    /**
     * Adds a value to the payload array if it's not null and not an empty array.
     *
     * @param array<string, mixed> $payload The payload array to add to
     * @param string $key The key to use in the payload
     * @param mixed $value The value to add (can be a scalar, array, or ArraySerializableInterface)
     */
    protected function addIfSet(array &$payload, string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (is_array($value) && $value === []) {
            return;
        }

        if ($value instanceof ArraySerializableInterface) {
            $payload[$key] = $value->toArray();
            return;
        }

        $payload[$key] = $value;
    }
}
