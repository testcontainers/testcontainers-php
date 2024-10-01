<?php

declare(strict_types=1);

namespace Testcontainers\ContainerClient;

use Docker\Docker as DockerClient;

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
            self::$dockerClient = DockerClient::create();
        }

        return self::$dockerClient;
    }

    /**
     * Injects a DockerClient instance for testing or special use cases.
     *
     * @param DockerClient $client The DockerClient instance to set.
     */
    public static function setDockerClient(DockerClient $client): void
    {
        self::$dockerClient = $client;
    }
}
