<?php

declare(strict_types=1);

namespace Testcontainers\Docker;

use RuntimeException;

/**
 * Configuration for connecting to the Docker daemon.
 *
 * This value object encapsulates all the configuration needed to connect to Docker,
 * including Unix socket paths, TCP endpoints, and TLS settings.
 */
final class DockerHostConfig
{
    public function __construct(
        public readonly string $baseUri,
        public readonly ?string $unixSocketPath,
        public readonly bool $tlsEnabled,
        public readonly bool $tlsVerify,
        public readonly ?string $certPath,
        public readonly ?string $apiVersion = null
    ) {
    }

    /**
     * Creates a DockerHostConfig from environment variables.
     *
     * Supports the following environment variables:
     * - DOCKER_HOST: The Docker daemon socket (unix:///var/run/docker.sock, tcp://localhost:2375, etc.)
     * - DOCKER_TLS_VERIFY: Set to '1' to enable TLS verification
     * - DOCKER_CERT_PATH: Path to directory containing cert.pem, key.pem, and ca.pem
     * - DOCKER_API_VERSION: API version to use (e.g., '1.43')
     */
    public static function fromEnvironment(): self
    {
        $dockerHost = getenv('DOCKER_HOST');

        if ($dockerHost === false || $dockerHost === '') {
            $dockerHost = \PHP_OS_FAMILY === 'Windows'
                ? 'npipe:////./pipe/docker_engine'
                : 'unix:///var/run/docker.sock';
        }

        return self::parse($dockerHost);
    }

    /**
     * Parses a Docker host string into a DockerHostConfig.
     *
     * @param string $dockerHost The Docker host string (e.g., 'unix:///var/run/docker.sock', 'tcp://localhost:2375')
     */
    public static function parse(string $dockerHost): self
    {
        $scheme = null;
        $host = null;
        $port = null;
        $path = null;

        $parts = parse_url($dockerHost);
        if ($parts !== false) {
            $scheme = $parts['scheme'] ?? null;
            $host = $parts['host'] ?? null;
            $port = $parts['port'] ?? null;
            $path = $parts['path'] ?? null;
        } elseif (str_starts_with($dockerHost, 'unix://')) {
            $scheme = 'unix';
            $path = substr($dockerHost, strlen('unix://'));
        } elseif (str_starts_with($dockerHost, 'npipe://')) {
            $scheme = 'npipe';
            $path = substr($dockerHost, strlen('npipe://'));
        } elseif (str_starts_with($dockerHost, '/')) {
            $scheme = 'unix';
            $path = $dockerHost;
        } else {
            throw new RuntimeException("Invalid DOCKER_HOST: {$dockerHost}");
        }

        if ($scheme === null && $path !== null) {
            $scheme = 'unix';
        }

        $scheme ??= 'unix';
        $host ??= 'localhost';

        $tlsVerifyEnv = getenv('DOCKER_TLS_VERIFY');
        $tlsVerify = $tlsVerifyEnv !== false && $tlsVerifyEnv !== '0';
        $certPath = getenv('DOCKER_CERT_PATH') ?: null;

        $baseUri = '';
        $unixSocketPath = null;
        $tlsEnabled = false;

        switch ($scheme) {
            case 'unix':
                $unixSocketPath = $path ?: '/var/run/docker.sock';
                $baseUri = 'http://localhost';
                break;
            case 'npipe':
                $unixSocketPath = $path ?: '//./pipe/docker_engine';
                $baseUri = 'http://localhost';
                break;
            case 'tcp':
                $tlsEnabled = $tlsVerify;
                $scheme = $tlsEnabled ? 'https' : 'http';
                $port = $port ?? ($tlsEnabled ? 2376 : 2375);
                $baseUri = sprintf('%s://%s:%d', $scheme, $host, $port);
                break;
            case 'http':
            case 'https':
                $tlsEnabled = $scheme === 'https' || $tlsVerify;
                $baseUri = $scheme . '://' . $host;
                if ($port !== null) {
                    $baseUri .= ':' . $port;
                }
                break;
            default:
                throw new RuntimeException("Unsupported Docker host scheme: {$scheme}");
        }

        $apiVersion = getenv('DOCKER_API_VERSION') ?: null;

        return new self($baseUri, $unixSocketPath, $tlsEnabled, $tlsVerify, $certPath, $apiVersion);
    }
}
