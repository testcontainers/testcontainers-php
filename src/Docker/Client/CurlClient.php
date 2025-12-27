<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Client;

use RuntimeException;
use Testcontainers\Docker\DockerResponse;

class CurlClient implements ClientInterface
{
    public function __construct(
        private string $baseUri,
        private ?string $unixSocketPath,
        private bool $tlsEnabled,
        private bool $tlsVerify,
        private ?string $certPath,
        private ?string $apiVersion = null
    ) {
    }

    public function request(string $method, string $path, array $query = [], ?string $body = null, array $headers = []): DockerResponse
    {
        $url = $this->buildUrl($path, $query);
        $ch = $this->initCurl($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        return $this->executeRequest($ch);
    }

    public function requestStream(string $method, string $path, $handle, array $query = [], array $headers = []): DockerResponse
    {
        $url = $this->buildUrl($path, $query);
        $ch = $this->initCurl($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $handle);

        $stats = fstat($handle);
        if (is_array($stats)) {
            curl_setopt($ch, CURLOPT_INFILESIZE, $stats['size']);
        }

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        return $this->executeRequest($ch);
    }

    private function initCurl(string $url): \CurlHandle
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        if ($this->unixSocketPath !== null) {
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $this->unixSocketPath);
        }

        if ($this->tlsEnabled) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->tlsVerify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->tlsVerify ? 2 : 0);

            if ($this->certPath !== null) {
                $certFile = rtrim($this->certPath, '/') . '/cert.pem';
                $keyFile = rtrim($this->certPath, '/') . '/key.pem';
                $caFile = rtrim($this->certPath, '/') . '/ca.pem';

                if (is_file($certFile)) {
                    curl_setopt($ch, CURLOPT_SSLCERT, $certFile);
                }
                if (is_file($keyFile)) {
                    curl_setopt($ch, CURLOPT_SSLKEY, $keyFile);
                }
                if (is_file($caFile)) {
                    curl_setopt($ch, CURLOPT_CAINFO, $caFile);
                }
            }
        }

        return $ch;
    }

    private function executeRequest(\CurlHandle $ch): DockerResponse
    {
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            $this->closeCurl($ch);
            throw new RuntimeException($error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->closeCurl($ch);

        return new DockerResponse($status, (string) $body);
    }

    private function closeCurl(\CurlHandle $ch): void
    {
        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUrl(string $path, array $query = []): string
    {
        $versionedPath = $this->apiVersion !== null
            ? '/v' . $this->apiVersion . $path
            : $path;

        $url = $this->baseUri . $versionedPath;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}
