# Redis

`RedisContainer` exposes port `6379` and waits for the log line `Ready to accept connections`.

## Requirements

- PHP package: `predis/predis`

```php
<?php

declare(strict_types=1);

use Predis\Client;
use Testcontainers\Modules\RedisContainer;

$container = (new RedisContainer())->start();

try {
    $redisClient = new Client([
        'host' => $container->getHost(),
        'port' => $container->getFirstMappedPort(),
    ]);

    $redisClient->ping();
    $redisClient->set('framework', 'testcontainers-php');
    echo (string) $redisClient->get('framework');
} finally {
    $container->stop();
}
```
