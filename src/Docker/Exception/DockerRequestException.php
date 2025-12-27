<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Exception;

use RuntimeException;

class DockerRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $statusCode,
        private string $responseBody
    ) {
        $fullMessage = sprintf('%s (HTTP %d)', $message, $statusCode);
        if ($responseBody !== '') {
            $fullMessage .= ': ' . $responseBody;
        }
        parent::__construct($fullMessage, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
