# Testcontainers for PHP

Testcontainers is a PHP package that makes it simple to create and clean up container-based dependencies for automated integration/smoke tests. The package is inspired by the [Testcontainers](https://www.testcontainers.org/) project for Java.

[@sironheart](https://github.com/sironheart) has annoyed me to test testcontainers, but it didn't existed in PHP yet.

## Installation

Add this to your project with composer

```bash
composer req --dev shyim/testcontainer
```
    
## Usage/Examples

### MySQL

```php
<?php

use Testcontainer\Container\MySQLContainer;

$container = new MySQLContainer('8.0');
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

$container = new MariaDBContainer('8.0');
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

### Redis

```php

use Testcontainer\Container\RedisContainer;

$container = new RedisContainer('6.0');

$container->run();

$redis = new \Redis();
$redis->connect($container->getAddress());

// Do something with redis
```

### OpenSearch

```php

use Testcontainer\Container\OpenSearchContainer;

$container = new OpenSearchContainer('2');
$container->disableSecurityPlugin();

$container->run();

// Do something with opensearch
```

## License

[MIT](https://choosealicense.com/licenses/mit/)

