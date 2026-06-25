<?php

declare(strict_types=1);

namespace Testcontainers\ContainerClient;

use Composer\InstalledVersions;
use Docker\Docker as DockerClient;
use Docker\DockerClientFactory;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\PluginClient;

class DockerContainerClient
{
    /**
     * @var DockerClient|null Singleton instance of DockerClient
     */
    private static ?DockerClient $dockerClient = null;

    private function __construct()
    {
    }

    /**
     * Returns the singleton DockerClient instance.
     *
     * @return DockerClient The singleton DockerClient instance.
     * @throws \RuntimeException If the DockerClient instance could not be created.
     */
    public static function getDockerClient(): DockerClient
    {
        if (self::$dockerClient === null) {
            try {
                $version = InstalledVersions::getPrettyVersion('testcontainers/testcontainers') ?? 'unknown';
            } catch (\OutOfBoundsException) {
                $version = 'unknown';
            }

            $version = str_replace('+no-version-set', '', $version);

            $baseHttpClient = DockerClientFactory::createFromEnv();

            $httpClient = new PluginClient(
                $baseHttpClient,
                [new HeaderDefaultsPlugin(['User-Agent' => 'tc-php/' . $version])]
            );

            self::$dockerClient = DockerClient::create($httpClient);
        }

        return self::$dockerClient;
    }

    /**
     * Injects a DockerClient instance for testing or special use cases.
     * Note: clients injected via this method will not have the tc-php User-Agent header applied automatically.
     *
     * @param DockerClient $client The DockerClient instance to set.
     */
    public static function setDockerClient(DockerClient $client): void
    {
        self::$dockerClient = $client;
    }
}
