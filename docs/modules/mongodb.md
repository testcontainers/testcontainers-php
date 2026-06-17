# MongoDB

`MongoDBContainer` configures credentials and waits until `mongosh` can execute a command.

## Requirements

- PHP extension: `ext-mongodb`

```php
<?php

declare(strict_types=1);

use Testcontainers\Modules\MongoDBContainer;

$container = (new MongoDBContainer())->start();

try {
    $pingResult = $container->exec([
        'mongosh',
        'admin',
        '-u',
        'test',
        '-p',
        'test',
        '--eval',
        '\'db.runCommand("ping").ok\'',
    ]);

    echo $pingResult;
} finally {
    $container->stop();
}
```
