# Usage

**As an example, let's spin up and test a Redis container.**


First, install dependencies:

```bash
composer require --dev testcontainers/testcontainers
composer require predis/predis
```

Next, we'll write a PHPUnit test using `GenericContainer` directly:

```php
<?php

declare(strict_types=1);

use Predis\Client;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForExec;

final class GenericRedisContainerTest extends TestCase
{
    #[Test]
    public function startsRedisWithGenericContainer(): void
    {
        $container = (new GenericContainer('redis:8'))
            ->withExposedPorts(6379)
            ->withWait(new WaitForExec(['redis-cli', 'ping']))
            ->start();

        try {
            $redis = new Client([
                'host' => $container->getHost(),
                'port' => $container->getMappedPort(6379),
            ]);

            $redis->set('hello', 'world');

            self::assertSame('world', (string) $redis->get('hello'));
        } finally {
            $container->stop();
        }
    }
}
```

Run the test, and after a few seconds, it passes!

!!! note
    Why did it take a few seconds?

    Because your container runtime first had to pull the image. If you run the test again, it'll run faster.

The complexity of configuring a container varies.

For Redis, it's pretty simple, we just expose a port. But for example, to define a GenericContainer for PostgreSQL, you'd need to configure multiple ports, environment variables for credentials, custom wait strategies, and more. For this reason there exists a catalogue of [pre-defined modules](https://testcontainers.com/modules/), which abstract away this complexity.

If a module exists for the container you want to use, it's highly recommended to use it.

For example, using the ready-made Redis module, the example above can be simplified:

```php
<?php

declare(strict_types=1);

use Predis\Client;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Testcontainers\Modules\RedisContainer;

final class RedisContainerTest extends TestCase
{
    #[Test]
    public function startsRedisWithModule(): void
    {
        $container = (new RedisContainer())->start();

        try {
            $redis = new Client([
                'host' => $container->getHost(),
                'port' => $container->getFirstMappedPort(),
            ]);

            $redis->set('hello', 'world');

            self::assertSame('world', (string) $redis->get('hello'));
        } finally {
            $container->stop();
        }
    }
}
```

!!! note
    `#[Test]` attributes require PHPUnit 10+.
