# MariaDB

`MariaDBContainer` configures MariaDB and waits for `mariadb-admin ping`.

## Requirements

- PHP extension: `ext-pdo_mysql`

```php
<?php

declare(strict_types=1);

use Testcontainers\Modules\MariaDBContainer;

$container = (new MariaDBContainer())
    ->withMariaDBDatabase('foo')
    ->withMariaDBUser('bar', 'baz')
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
