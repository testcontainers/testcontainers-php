<?php

namespace Testcontainers\ContainerRuntime;

use Docker\Docker as DockerClient;

class ContainerRuntimeClient
{
    /**
     * @var DockerClient|null Singleton instance of DockerClient
     */
    private static ?DockerClient $dockerClient = null;

    /**
     * Private constructor to prevent creating instance outside the class.
     */
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
