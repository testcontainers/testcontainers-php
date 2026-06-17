# MySQL

`MySQLContainer` configures MySQL and waits for `mysqladmin ping`.

## Requirements

- PHP extension: `ext-pdo_mysql`

```php
<?php

declare(strict_types=1);

use Testcontainers\Modules\MySQLContainer;

$container = (new MySQLContainer())
    ->withMySQLDatabase('foo')
    ->withMySQLUser('bar', 'baz')
    ->start();

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d', $container->getHost(), $container->getFirstMappedPort()),
        'bar',
        'baz',
    );

    $query = $pdo->query('SHOW databases');
    $databases = $query->fetchAll(PDO::FETCH_COLUMN);
} finally {
    $container->stop();
}
```
