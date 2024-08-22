<?php

declare(strict_types=1);

namespace Testcontainers;

use Testcontainers\Container\GenericContainer;

class Registry
{
    private static bool $registeredCleanup = false;

    /**
     * @var array<int|string, GenericContainer>
     */
    private static array $registry = [];

    public static function add(GenericContainer $container): void
    {
        self::$registry[spl_object_id($container)] = $container;

        if (!self::$registeredCleanup) {
            register_shutdown_function([self::class, 'cleanup']);
            self::$registeredCleanup = true;
        }
    }

    public static function remove(GenericContainer $container): void
    {
        unset(self::$registry[spl_object_id($container)]);
    }

    public static function cleanup(): void
    {
        foreach (self::$registry as $container) {
            $container->remove();
        }
    }
}
