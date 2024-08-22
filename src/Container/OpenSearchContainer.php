<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Wait\WaitForHttp;

class OpenSearchContainer extends GenericContainer
{
    private function __construct(string $version)
    {
        parent::__construct('opensearchproject/opensearch:' . $version);
        $this->withEnvironment('discovery.type', 'single-node');
        $this->withEnvironment('OPENSEARCH_INITIAL_ADMIN_PASSWORD', 'c3o_ZPHo!');
        $this->withWait(WaitForHttp::make(9200));
    }

    public static function make(string $version = 'latest'): self
    {
        return new self($version);
    }

    public function disableSecurityPlugin(): self
    {
        $this->withEnvironment('plugins.security.disabled', 'true');

        return $this;
    }
}
