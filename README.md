# Testcontainers for PHP

Testcontainers is a PHP package that makes it simple to create and clean up container-based dependencies for automated integration/smoke tests. The package is inspired by the [Testcontainers](https://www.testcontainers.org/) project for Java.

[@sironheart](https://github.com/sironheart) has annoyed me to test testcontainers, but it didn't existed in PHP yet.

## Installation

Add this to your project with composer

```bash
composer req --dev shyim/testcontainer
```
    
## Usage/Examples

### Starting a general Container

```php
<?php

use Testcontainer\Container\MySQLContainer;

$container = Container::make('nginx:alpine');

// set an environment variable
$container->withEnvironment('name', 'var');

// enable health check for an container
$container->withHealthCheckCommand('curl --fail localhost');

// mount current dir to /var/www/html
$container->withMount(__DIR__, '/var/www/html');
```

Normally you have to wait until the Container is ready. so for this you can define an wait rule:

```php

// Run mysqladmin ping until the command returns exit code 0
$container->withWait(new WaitForExec(['mysqladmin', 'ping', '-h', '127.0.0.1']));

$container->withWait(new WaitForExec(['mysqladmin', 'ping', '-h', '127.0.0.1']), function(Process $process) {
    // throw exception if process result is bad
});

// Wait until that message is in the logs
$container->withWait(new WaitForLog('Ready to accept connections'));


// Wait for an http request to succeed
$container->withWait(WaitForHttp::make($port, $method = 'GET', $path = '/'));

// Wait until the docker heartcheck is green
$container->withWait(new WaitForHealthCheck());
```

### MySQL

```php
<?php

use Testcontainer\Container\MySQLContainer;

$container = MySQLContainer::make('8.0');
$container->withMySQLDatabase('foo');
$container->withMySQLUser('bar', 'baz');

$container->run();

$pdo = new \PDO(
    sprintf('mysql:host=%s;port=3306', $container->getAddress()),
    'bar',
    'baz',
);

// Do something with pdo
```

### MariaDB

```php
<?php

use Testcontainer\Container\MariaDBContainer;

$container = MariaDBContainer::make('8.0');
$container->withMariaDBDatabase('foo');
$container->withMariaDBUser('bar', 'baz');

$container->run();

$pdo = new \PDO(
    sprintf('mysql:host=%s;port=3306', $container->getAddress()),
    'bar',
    'baz',
);

// Do something with pdo
```

### PostgreSQL

```php
<?php

use Testcontainer\Container\PostgresContainer;

$container = PostgresContainer::make('15.0', 'password');
$container->withPostgresDatabase('database');
$container->withPostgresUser('username');

$container->run();

$pdo = new \PDO(
    sprintf('pgsql:host=%s;port=5432;dbname=database', $container->getAddress()),
    'username',
    'password',
);

// Do something with pdo
```

### Redis

```php

use Testcontainer\Container\RedisContainer;

$container = RedisContainer::make('6.0');

$container->run();

$redis = new \Redis();
$redis->connect($container->getAddress());

// Do something with redis
```

### OpenSearch

```php

use Testcontainer\Container\OpenSearchContainer;

$container = OpenSearchContainer::make('2');
$container->disableSecurityPlugin();

$container->run();

// Do something with opensearch
```

## License

[MIT](https://choosealicense.com/licenses/mit/)

