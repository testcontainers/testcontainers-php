<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Testcontainers\Modules\OpenSearchContainer;

class OpenSearchContainerTest extends ContainerTestCase
{
    public function setUp(): void
    {
        $this->container = (new OpenSearchContainer())
            ->withDisabledSecurityPlugin()
            ->start();
    }

    /**
     * @throws \JsonException
     */
    public function testOpenSearch(): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf(
            'http://%s:%d',
            $this->container->getHost(),
            $this->container->getFirstMappedPort()
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = (string) curl_exec($ch);

        $this->assertNotEmpty($response);

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('cluster_name', $data);

        $this->assertEquals('docker-cluster', $data['cluster_name']);
    }
}
