<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Testcontainers\Container\OpenSearchContainer;

class OpenSearchContainerTest extends ContainerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::$container = (new OpenSearchContainer())
            ->withDisabledSecurityPlugin()
            ->start();
    }

    /**
     * @throws \JsonException
     */
    public function testOpenSearch(): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf('http://%s:%d', self::$container->getAddress(), 9200));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = (string) curl_exec($ch);

        $this->assertNotEmpty($response);

        /** @var array{cluster_name: string} $data */
        $data = json_decode($response, true, JSON_THROW_ON_ERROR, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('cluster_name', $data);

        $this->assertEquals('docker-cluster', $data['cluster_name']);
    }
}
