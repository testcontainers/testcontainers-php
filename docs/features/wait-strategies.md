# Wait strategies

Wait strategies define when a container is ready to use.

`GenericContainer` defaults to a running-state check (`WaitForContainer`).  
For application readiness, prefer explicit strategies described below.

## Host port (default-friendly)

```php
<?php

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForHostPort;

$container = (new GenericContainer('redis:7'))
    ->withExposedPorts(6379)
    ->withWait(new WaitForHostPort())
    ->start();
```

## Log output

```php
use Testcontainers\Wait\WaitForLog;

$container = (new GenericContainer('redis:7'))
    ->withExposedPorts(6379)
    ->withWait(new WaitForLog('Ready to accept connections'))
    ->start();
```

With regular expression matching:

```php
$container = (new GenericContainer('opensearchproject/opensearch:latest'))
    ->withExposedPorts(9200)
    ->withWait(new WaitForLog('/\]\s+started\?\[/', true, 30_000))
    ->start();
```

## HTTP checks

```php
use Testcontainers\Wait\WaitForHttp;

$container = (new GenericContainer('nginx:alpine'))
    ->withExposedPorts(80)
    ->withWait(
        (new WaitForHttp(80))
            ->withPath('/')
            ->withExpectedStatusCode(200)
    )
    ->start();
```

## Exec command

```php
use Testcontainers\Wait\WaitForExec;

$container = (new GenericContainer('mysql:8.0'))
    ->withExposedPorts(3306)
    ->withEnvironment(['MYSQL_ROOT_PASSWORD' => 'root'])
    ->withWait(new WaitForExec(['mysqladmin', 'ping', '-h', '127.0.0.1']))
    ->start();
```

With custom validation:

```php
$container = (new GenericContainer('mysql:8.0'))
    ->withExposedPorts(3306)
    ->withEnvironment(['MYSQL_ROOT_PASSWORD' => 'root'])
    ->withWait(
        new WaitForExec(
            ['mysqladmin', 'ping', '-h', '127.0.0.1'],
            static function ($exitCode, $output): bool {
                return $exitCode === 0 && str_contains($output, 'mysqld is alive');
            }
        )
    )
    ->start();
```

## Docker health check

```php
use Testcontainers\Wait\WaitForHealthCheck;

$container = (new GenericContainer('alpine'))
    ->withCommand(['tail', '-f', '/dev/null'])
    ->withHealthCheckCommand('echo "healthy" || exit 1')
    ->withWait(new WaitForHealthCheck())
    ->start();
```

!!! tip
    You can tune timeout and polling intervals in wait strategy constructors.

Related docs: [containers](containers.md), [networking](networking.md), [troubleshooting](../troubleshooting.md).
