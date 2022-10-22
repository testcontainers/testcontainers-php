<?php

declare(strict_types=1);

namespace Testcontainer;

use Testcontainer\Container\Container;

class Registry
{
    private static bool $registeredCleanup = false;

    /**
     * @var array<int|string, Container>
     */
    private static array $registry = [];

    public static function add(Container $container): void
    {
        self::$registry[spl_object_id($container)] = $container;

        if (!self::$registeredCleanup) {
            register_shutdown_function([self::class, 'cleanup']);
            self::$registeredCleanup = true;
        }
    }

    public static function remove(Container $container): void
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
