<?php

declare(strict_types=1);

namespace Testcontainers\ContainerClient;

use Composer\InstalledVersions;
use Docker\Docker as DockerClient;
use Docker\DockerClientFactory;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\PluginClient;
use Psr\Http\Client\ClientInterface;

class DockerContainerClient
{
    /**
     * @var DockerClient|null Singleton instance of DockerClient
     */
    private static ?DockerClient $dockerClient = null;

    /**
     * @var (callable(): ClientInterface)|null Factory for the base HTTP client.
     *     When null, DockerClientFactory::createFromEnv() is used.
     */
    private static $httpClientFactory = null;

    /**
     * @var (callable(ClientInterface): DockerClient)|null Factory for the Docker client.
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
            $version = static::resolveVersion();

            $baseHttpClient = self::createHttpClient();

            $httpClient = new PluginClient(
                $baseHttpClient,
                [new HeaderDefaultsPlugin(['User-Agent' => 'tc-php/' . $version])]
            );

            self::$dockerClient = self::createDockerClient($httpClient);
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
     * Returns the base HTTP client, using the injected factory if set.
     */
    private static function createHttpClient(): ClientInterface
    {
        return self::$httpClientFactory !== null
            ? (self::$httpClientFactory)()
            : DockerClientFactory::createFromEnv();
    }

    /**
     * Builds the DockerClient from the given HTTP client, using the injected factory if set.
     */
    private static function createDockerClient(ClientInterface $httpClient): DockerClient
    {
        return self::$dockerClientFactory !== null
            ? (self::$dockerClientFactory)($httpClient)
            : DockerClient::create($httpClient);
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
     * Resets the injectable factories to their defaults.
     * For use in tests only — do not call in production code.
     *
     * @param (callable(): ClientInterface)|null $httpClientFactory
     * @param (callable(ClientInterface): DockerClient)|null $dockerClientFactory
     */
    public static function setFactories(?callable $httpClientFactory, ?callable $dockerClientFactory): void
    {
        self::$httpClientFactory = $httpClientFactory;
        self::$dockerClientFactory = $dockerClientFactory;
    }

    /**
     * Resets the injectable factories to their defaults (both null).
     * For use in tests only — do not call in production code.
     */
    public static function resetFactories(): void
    {
        self::$httpClientFactory = null;
        self::$dockerClientFactory = null;
    }
}
