# Containers

## Starting a container

Start any image with `GenericContainer`:

```php
<?php

use Testcontainers\Container\GenericContainer;

$container = (new GenericContainer('alpine:3.20'))
    ->withCommand(['sleep', 'infinity'])
    ->start();
```

## Common container options

### Environment variables

```php
$container = (new GenericContainer('alpine:3.20'))
    ->withEnvironment([
        'APP_ENV' => 'test',
        'FEATURE_X' => 'enabled',
    ])
    ->start();
```

### Exposed ports

```php
$container = (new GenericContainer('nginx:alpine'))
    ->withExposedPorts(80)
    ->start();

$host = $container->getHost();
$port = $container->getMappedPort(80);
```

### Files and directories

```php
$container = (new GenericContainer('alpine:3.20'))
    ->withCommand(['sleep', 'infinity'])
    ->withCopyFilesToContainer([
        ['source' => __DIR__ . '/app.conf', 'target' => '/etc/app.conf'],
    ])
    ->withCopyContentToContainer([
        ['content' => 'hello from php', 'target' => '/tmp/message.txt'],
    ])
    ->start();
```

### Networking

`withNetwork()` connects the container to an existing Docker network. Create the network before starting the container, for example with `docker network create my-test-network`.

```php
$container = (new GenericContainer('alpine:3.20'))
    ->withNetwork('my-test-network')
    ->withAliases(['service-a'])
    ->start();
```

### User, working directory, labels, and mounts

```php
$container = (new GenericContainer('alpine:3.20'))
    ->withUser('1000:1000')
    ->withWorkingDir('/app')
    ->withLabels(['suite' => 'integration'])
    ->withMount(__DIR__, '/workspace')
    ->start();
```

## Stopping and restarting

```php
$container->restart();
$container->stop();
```

`stop()` stops and removes the container.

Related docs: [wait strategies](wait-strategies.md), [networking](networking.md).
