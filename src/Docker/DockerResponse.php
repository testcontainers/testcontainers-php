<?php

declare(strict_types=1);

namespace Testcontainers\Docker;

use RuntimeException;

class DockerResponse
{
    public function __construct(private int $statusCode, private string $body)
    {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Returns the raw response body as a string.
     */
    public function getBodyContents(): string
    {
        return $this->body;
    }

    /**
     * Decodes the response body as JSON and returns the resulting array.
     *
     * @return array<string, mixed>
     * @throws RuntimeException If the body is not valid JSON
     */
    public function getJson(): array
    {
        if ($this->body === '') {
            return [];
        }

        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON response from Docker');
        }

        return $data;
    }

    /**
     * @deprecated Use getBodyContents() instead. Will be removed in a future version.
     */
    public function getBody(): DockerResponseBody
    {
        return new DockerResponseBody($this->body);
    }
}
