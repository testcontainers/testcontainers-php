<?php

declare(strict_types=1);

namespace Testcontainers\Modules;

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForLog;

class OpenSearchContainer extends GenericContainer
{
    public function __construct(string $version = 'latest')
    {
        parent::__construct('opensearchproject/opensearch:' . $version);
        $this->withExposedPorts(9200);
        $this->withEnvironment('discovery.type', 'single-node');
        $this->withEnvironment('OPENSEARCH_INITIAL_ADMIN_PASSWORD', 'c3o_ZPHo!');
        $this->withWait(new WaitForLog(
            '/\]\s+started\?\[/',
            true,
            30000
        ));
    }

    public function withDisabledSecurityPlugin(): self
    {
        $this->withEnvironment('plugins.security.disabled', 'true');

        return $this;
    }
}
