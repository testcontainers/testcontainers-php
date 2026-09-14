<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Testcontainers\Container\HttpMethod;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Exception\ContainerWaitingTimeoutException;

class WaitForHttp extends BaseWaitStrategy
{
    protected HttpMethod $method = HttpMethod::GET;

    protected string $path = '/';

    protected string $protocol = 'http';

    protected int $expectedStatusCode = 200;

    protected bool $allowInsecure = false;

    /**
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * @var int Timeout in milliseconds for reading the response
     */
    protected int $readTimeout = 1000;

    protected ?int $hostPort = null;

    public function __construct(
        protected ?int $containerPort = null,
        int $timeout = 10000,
        int $pollInterval = 500
    ) {
        parent::__construct($timeout, $pollInterval);
    }

    /**
     * @param HttpMethod|value-of<HttpMethod> $method
     */
    public function withMethod(HttpMethod | string $method): self
    {
        if (is_string($method)) {
            $method = HttpMethod::fromString($method);
        }
        $this->method = $method;
        return $this;
    }

    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function withExpectedStatusCode(int $statusCode): self
    {
        $this->expectedStatusCode = $statusCode;
        return $this;
    }

    public function usingHttps(): self
    {
        $this->protocol = 'https';
        return $this;
    }

    public function allowInsecure(): self
    {
        $this->allowInsecure = true;
        return $this;
    }

    public function withReadTimeout(int $timeout): self
    {
        $this->readTimeout = $timeout;
        return $this;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    public function wait(StartedTestContainer $container): void
    {
        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsedTime = (microtime(true) * 1000) - $startTime;

            if ($elapsedTime > $this->timeout) {
                throw new ContainerWaitingTimeoutException($container->getId());
            }

            try {
                $this->resolveHostPort($container);
                $containerAddress = $container->getHost();

                $url = "{$this->protocol}://{$containerAddress}:{$this->hostPort}{$this->path}";
                $responseCode = $this->makeHttpRequest($url);

                if ($responseCode === $this->expectedStatusCode) {
                    return; // Container is ready
                }
            } catch (\RuntimeException) {
                // Port resolution or HTTP request failed, we'll try again until timeout
            }

            usleep($this->pollInterval * 1000);
        }
    }

    /**
     * @param non-empty-string $url
     */
    private function makeHttpRequest(string $url): int
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method->value);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); // No need for a response body, just headers
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->readTimeout);

        // Allow insecure connections if requested
        if ($this->allowInsecure) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        // Add custom headers
        if (!empty($this->headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(static fn ($k, $v) => "$k: $v", array_keys($this->headers), $this->headers));
        }

        curl_exec($ch);
        return curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    private function resolveHostPort(StartedTestContainer $container): void
    {
        if ($this->hostPort !== null) {
            return; // Port already resolved
        }

        $this->hostPort = $this->containerPort === null
            ? $container->getFirstMappedPort()
            : $container->getMappedPort($this->containerPort);
    }
}
