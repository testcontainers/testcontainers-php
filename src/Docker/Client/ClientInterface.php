<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Client;

use Testcontainers\Docker\DockerResponse;

interface ClientInterface
{
    /**
     * @param non-empty-string $method
     * @param non-empty-string $path
     * @param array<string, mixed> $query
     * @param list<string> $headers
     */
    public function request(string $method, string $path, array $query = [], ?string $body = null, array $headers = []): DockerResponse;

    /**
     * @param non-empty-string $method
     * @param non-empty-string $path
     * @param resource $handle
     * @param array<string, mixed> $query
     * @param list<string> $headers
     */
    public function requestStream(string $method, string $path, $handle, array $query = [], array $headers = []): DockerResponse;
}
