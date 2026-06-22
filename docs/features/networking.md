# Networking

Testcontainers for PHP maps container ports to random host ports by default.

## Access a service from your test process

Use `getHost()` and `getMappedPort()`:

```php
<?php

declare(strict_types=1);

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForHttp;

$container = (new GenericContainer('nginx:alpine'))
    ->withExposedPorts(80)
    ->withWait((new WaitForHttp(80))->withPath('/'))
    ->start();

$url = sprintf('http://%s:%d', $container->getHost(), $container->getMappedPort(80));
echo $url . PHP_EOL;

$container->stop();
```

## Use the first mapped port

If a container exposes only one port, use `getFirstMappedPort()`:

```php
$port = $container->getFirstMappedPort();
```

## Join a Docker network

Connect multiple containers to the same existing Docker network and use aliases. Testcontainers for PHP does not create Docker networks, so create the network before starting containers:

```bash
docker network create my-test-network
```

```php
<?php

declare(strict_types=1);

use Testcontainers\Container\GenericContainer;

$container = (new GenericContainer('alpine'))
    ->withCommand(['tail', '-f', '/dev/null'])
    ->withNetwork('my-test-network')
    ->withAliases(['service-a'])
    ->start();

$container->stop();
```

## Notes

- Do not hardcode localhost ports in tests.
- Always resolve endpoints from `getHost()` and mapped ports.
- Named networks and aliases are useful for container-to-container communication.

Related docs: [containers](containers.md), [configuration](../configuration.md), [troubleshooting](../troubleshooting.md).
