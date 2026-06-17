# OpenSearch

`OpenSearchContainer` configures single-node mode and waits for startup logs.

```php
<?php

declare(strict_types=1);

use Testcontainers\Modules\OpenSearchContainer;

$container = (new OpenSearchContainer())
    ->withDisabledSecurityPlugin()
    ->start();

try {
    $ch = curl_init();
    curl_setopt(
        $ch,
        CURLOPT_URL,
        sprintf('http://%s:%d', $container->getHost(), $container->getFirstMappedPort())
    );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = (string) curl_exec($ch);
    $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

    echo (string) $data['cluster_name'];
} finally {
    $container->stop();
}
```
