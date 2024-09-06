# Testcontainers for PHP

Testcontainers is a PHP package that makes it simple to create and clean up container-based dependencies for automated integration/smoke tests. The package is inspired by the [Testcontainers](https://www.testcontainers.org/) project for Java.

## Installation

Add this to your project with composer

```bash
composer req --dev testcontainers/testcontainers
```
    
## Usage/Examples

### Starting a general Container

```php
<?php

use Testcontainers\Container\GenericContainer;

$container = new GenericContainer('nginx:alpine');

// set an environment variable
$container->withEnvironment([
    'key1' => 'val1',
    'key2' => 'val2'
]);

// enable health check for an container
$container->withHealthCheckCommand('curl --fail localhost');

// mount current dir to /var/www/html
$container->withMount(__DIR__, '/var/www/html');
```

Normally you have to wait until the Container is ready. so for this you can define an wait rule:

```php

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForExec;
use Testcontainers\Wait\WaitForLog;
use Testcontainers\Wait\WaitForHttp;
use Testcontainers\Wait\WaitForHealthCheck;

$container = new GenericContainer('nginx:alpine');

// Run mysqladmin ping until the command returns exit code 0
$container->withWait(new WaitForExec(['mysqladmin', 'ping', '-h', '127.0.0.1']));

$container->withWait(new WaitForExec(['mysqladmin', 'ping', '-h', '127.0.0.1']), function($exitCode, $contents) {
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

use Testcontainers\Modules\MySQLContainer;

$container = (new MySQLContainer('8.0'))
    ->withMySQLDatabase('foo')
    ->withMySQLUser('bar', 'baz')
    ->start();

$pdo = new \PDO(
    sprintf(
        'mysql:host=%s;port=%d',
        $container->getHost(),
        $container->getFirstMappedPort()
    ),
    'bar',
    'baz',
);

// Do something with pdo
```

### MariaDB

```php
<?php

use Testcontainers\Modules\MariaDBContainer;

$container = $container = (new MariaDBContainer())
    ->withMariaDBDatabase('foo')
    ->withMariaDBUser('bar', 'baz')
    ->start();

$pdo = new \PDO(
    sprintf(
        'mysql:host=%s;port=%d',
        $container->getHost(),
        $container->getFirstMappedPort()
    ),
    'bar',
    'baz',
);

// Do something with pdo
```

### PostgreSQL

```php
<?php

use Testcontainers\Modules\PostgresContainer;

$container = (new PostgresContainer())
    ->withPostgresUser('bar')
    ->withPostgresDatabase('foo')
    ->start();

$pdo = new \PDO(
    sprintf(
        'pgsql:host=%s;port=%d;dbname=foo',
        self::$container->getHost(),
        self::$container->getFirstMappedPort()
    ),
    'bar',
    'test',
);

// Do something with pdo
```

### Redis

```php

use Testcontainers\Modules\RedisContainer;

$container = (new RedisContainer())
    ->start();

$redis = new \Redis();
$redis->connect($container->getHost(), $container->getFirstMappedPort());

// Do something with redis
```

### OpenSearch

```php

use Testcontainers\Modules\OpenSearchContainer;

$container = (new OpenSearchContainer())
    ->withDisabledSecurityPlugin()
    ->start();

// Do something with opensearch
```

### Use with symfony

```yaml
# config/packages/test/services.yaml

parameters:
  'doctrine.dbal.connection_factory.class': App\Tests\TestConnectionFactory
```

```php

namespace App\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Tools\DsnParser;
use Testcontainers\Modules\PostgresContainer;

class TestConnectionFactory extends ConnectionFactory
{
    static $testDsn;

    public function __construct(array $typesConfig, ?DsnParser $dsnParser = null)
    {
        if (!$this::$testDsn) {
            $psql = (new PostgresContainer())
                ->withPostgresUser('user')
                ->withPostgresPassword('password')
                ->withPostgresDatabase('database')
                ->start();
            $this::$testDsn = sprintf('postgresql://user:password@%s:%d/database?serverVersion=14&charset=utf8', $psql->getAddress(), $psql->getFirstMappedPort());
        }
        parent::__construct($typesConfig, $dsnParser);
    }


    public function createConnection(array $params, ?Configuration $config = null, ?EventManager $eventManager = null, array $mappingTypes = [])
    {
        $params['url'] = $this::$testDsn;
        return parent::createConnection($params, $config, $eventManager, $mappingTypes);
    }

}
```

## License

[MIT](https://choosealicense.com/licenses/mit/)

