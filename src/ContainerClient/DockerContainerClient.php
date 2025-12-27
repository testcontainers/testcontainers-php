<?php

declare(strict_types=1);

namespace Testcontainers\ContainerClient;

use Composer\InstalledVersions;
use Testcontainers\Docker\DockerClient;

class DockerContainerClient
{
    /**
     * @var DockerClient|null Singleton instance of DockerClient
     */
    private static ?DockerClient $dockerClient = null;

    /**
     * @var (callable(string): DockerClient)|null Factory for the Docker client, receiving the User-Agent string.
     *     When null, DockerClient::create() is used.
     */
    private static $dockerClientFactory = null;

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
            $userAgent = 'tc-php/' . static::resolveVersion();

            self::$dockerClient = self::$dockerClientFactory !== null
                ? (self::$dockerClientFactory)($userAgent)
                : DockerClient::create($userAgent);
        }

        return self::$dockerClient;
    }

    /**
     * Resolves the package version string used in the User-Agent header.
     *
     * Returns the pretty version of the installed package, with the
     * '+no-version-set' build-metadata suffix stripped. Falls back to
     * 'unknown' if the package is not found in the Composer runtime data.
     *
     * @param string $package Composer package name to resolve; override in tests
     *                        to exercise the OutOfBoundsException fallback path.
     */
    protected static function resolveVersion(string $package = 'testcontainers/testcontainers'): string
    {
        try {
            $version = InstalledVersions::getPrettyVersion($package) ?? 'unknown';
        } catch (\OutOfBoundsException) {
            $version = 'unknown';
        }

        return str_replace('+no-version-set', '', $version);
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

    /**
     * Injects a factory used to build the DockerClient from the User-Agent string.
     * For use in tests only — do not call in production code.
     *
     * @param (callable(string): DockerClient)|null $dockerClientFactory
     */
    public static function setDockerClientFactory(?callable $dockerClientFactory): void
    {
        self::$dockerClientFactory = $dockerClientFactory;
    }

    /**
     * Resets the injectable factory to its default (null).
     * For use in tests only — do not call in production code.
     */
    public static function resetDockerClientFactory(): void
    {
        self::$dockerClientFactory = null;
    }
}
