<?php

declare(strict_types=1);

namespace Testcontainers\ContainerClient;

use Composer\InstalledVersions;
use RuntimeException;
use Testcontainers\Docker\Cli\CliDockerClient;
use Testcontainers\Docker\DockerClient;
use Testcontainers\Docker\DockerClientInterface;

class DockerContainerClient
{
    public const ADAPTER_API = 'api';
    public const ADAPTER_CLI = 'cli';

    /**
     * @var DockerClientInterface|null Singleton instance of the Docker client
     */
    private static ?DockerClientInterface $dockerClient = null;

    /**
     * @var (callable(string): DockerClientInterface)|null Factory for the Docker client, receiving the User-Agent string.
     *     When null, the adapter named in TESTCONTAINERS_CLIENT is created.
     */
    private static $dockerClientFactory = null;

    private function __construct()
    {
    }

    /**
     * Returns the singleton Docker client instance.
     *
     * The adapter is chosen by the TESTCONTAINERS_CLIENT environment variable:
     *  - "api" (default): Docker Engine HTTP API over the socket/TCP endpoint from DOCKER_HOST
     *  - "cli": a Docker-compatible command line binary, see TESTCONTAINERS_CLI_BINARY (default "docker")
     *
     * @throws RuntimeException If the client could not be created.
     */
    public static function getDockerClient(): DockerClientInterface
    {
        if (self::$dockerClient === null) {
            $userAgent = 'tc-php/' . static::resolveVersion();

            self::$dockerClient = self::$dockerClientFactory !== null
                ? (self::$dockerClientFactory)($userAgent)
                : self::createFromEnvironment($userAgent);
        }

        return self::$dockerClient;
    }

    /**
     * @param non-empty-string $userAgent
     */
    private static function createFromEnvironment(string $userAgent): DockerClientInterface
    {
        $adapter = getenv('TESTCONTAINERS_CLIENT') ?: self::ADAPTER_API;

        return match (strtolower($adapter)) {
            self::ADAPTER_API => DockerClient::create($userAgent),
            self::ADAPTER_CLI => CliDockerClient::create(),
            default => throw new RuntimeException(sprintf(
                'Unsupported TESTCONTAINERS_CLIENT value "%s"; expected "%s" or "%s"',
                $adapter,
                self::ADAPTER_API,
                self::ADAPTER_CLI
            )),
        };
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
     * @param DockerClientInterface $client The client instance to set.
     */
    public static function setDockerClient(DockerClientInterface $client): void
    {
        self::$dockerClient = $client;
    }

    /**
     * Injects a factory used to build the DockerClient from the User-Agent string.
     * For use in tests only — do not call in production code.
     *
     * @param (callable(string): DockerClientInterface)|null $dockerClientFactory
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
