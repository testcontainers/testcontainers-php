# PostgreSQL

`PostgresContainer` sets `POSTGRES_USER`, `POSTGRES_PASSWORD`, and `POSTGRES_DB`, then waits with `pg_isready`.

## Requirements

- PHP extension: `ext-pdo_pgsql`

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Testcontainers\Modules\PostgresContainer;

$container = (new PostgresContainer())
    ->withPostgresUser('bar')
    ->withPostgresDatabase('foo')
    ->start();

try {
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%d;dbname=foo', $container->getHost(), $container->getFirstMappedPort()),
        'bar',
        'test',
    );

    $query = $pdo->query('SELECT datname FROM pg_database');
    $databases = $query->fetchAll(PDO::FETCH_COLUMN);
} finally {
    $container->stop();
}
```
