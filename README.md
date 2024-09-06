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

$container = new GenericContainer::make('nginx:alpine');

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

use Testcontainers\Modules\MySQLContainer;

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

use Testcontainers\Modules\MariaDBContainer;

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

use Testcontainers\Modules\PostgresContainer;

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

use Testcontainers\Modules\RedisContainer;

$container = RedisContainer::make('6.0');

$container->run();

$redis = new \Redis();
$redis->connect($container->getAddress());

// Do something with redis
```

### OpenSearch

```php

use Testcontainers\Modules\OpenSearchContainer;

$container = OpenSearchContainer::make('2');
$container->disableSecurityPlugin();

$container->run();

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
            $psql = PostgresContainer::make('14.0', 'password');
            $psql->withPostgresDatabase('database');
            $psql->withPostgresUser('user');
            $psql->run();
            $this::$testDsn = sprintf('postgresql://user:password@%s:5432/database?serverVersion=14&charset=utf8', $psql->getAddress());
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

