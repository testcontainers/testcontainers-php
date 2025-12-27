<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class NetworkIPAM
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return array<int, NetworkIPAMConfig>
     */
    public function getConfig(): array
    {
        $configs = $this->data['Config'] ?? [];
        if (!is_array($configs)) {
            return [];
        }

        $result = [];
        foreach ($configs as $config) {
            if (!is_array($config)) {
                continue;
            }
            $result[] = new NetworkIPAMConfig($config);
        }

        return $result;
    }
}
