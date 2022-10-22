<?php

declare(strict_types=1);

namespace Testcontainer\Container;

use Testcontainer\Wait\WaitForHttp;

class OpenSearchContainer extends Container
{
    public function __construct(string $version = 'latest')
    {
        parent::__construct('opensearchproject/opensearch:' . $version);
        $this->withEnvironment('discovery.type', 'single-node');
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
