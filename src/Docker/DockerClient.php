<?php

declare(strict_types=1);

namespace Testcontainers\Docker;

use Testcontainers\Docker\Client\ClientInterface;
use Testcontainers\Docker\Client\CurlClient;
use Testcontainers\Docker\Exception\ContainerCreateNotFoundException;
use Testcontainers\Docker\Exception\DockerRequestException;
use Testcontainers\Docker\Model\ContainerCreateResponse;
use Testcontainers\Docker\Model\ContainersCreatePostBody;
use Testcontainers\Docker\Model\ContainersIdExecPostBody;
use Testcontainers\Docker\Model\ContainersIdJsonGetResponse200;
use Testcontainers\Docker\Model\ExecIdJsonGetResponse200;
use Testcontainers\Docker\Model\IdResponse;
use Testcontainers\Docker\Model\Network;
use Testcontainers\Docker\Stream\CreateImageStream;

class DockerClient
{
    public const FETCH_RESPONSE = 1;

    public function __construct(
        private ClientInterface $client
    ) {
    }

    public static function create(): self
    {
        $config = DockerHostConfig::fromEnvironment();

        return new self(new CurlClient(
            $config->baseUri,
            $config->unixSocketPath,
            $config->tlsEnabled,
            $config->tlsVerify,
            $config->certPath,
            $config->apiVersion
        ));
    }

    /**
     * @param array<string, mixed> $query
     */
    public function containerCreate(ContainersCreatePostBody $body, array $query = []): ?ContainerCreateResponse
    {
        $response = $this->requestJson('POST', '/containers/create', $query, $body->toArray());

        if ($response->getStatusCode() === 404) {
            throw new ContainerCreateNotFoundException('Docker image not found');
        }

        $this->assertSuccess($response, 'Create container');

        return new ContainerCreateResponse($this->decodeResponse($response));
    }

    public function containerStart(string $id): void
    {
        $response = $this->request('POST', "/containers/{$id}/start");
        $this->assertStatus($response, 'Start container', [204, 304]);
    }

    public function containerStop(string $id): void
    {
        $response = $this->request('POST', "/containers/{$id}/stop");
        $this->assertStatus($response, 'Stop container', [204, 304]);
    }

    public function containerDelete(string $id): void
    {
        $response = $this->request('DELETE', "/containers/{$id}");
        $this->assertStatus($response, 'Delete container', [204, 404]);
    }

    public function containerRestart(string $id): void
    {
        $response = $this->request('POST', "/containers/{$id}/restart");
        $this->assertSuccess($response, 'Restart container');
    }

    public function containerExec(string $id, ContainersIdExecPostBody $body): ?IdResponse
    {
        $response = $this->requestJson('POST', "/containers/{$id}/exec", [], $body->toArray());
        $this->assertSuccess($response, 'Create exec');

        return new IdResponse($this->decodeResponse($response));
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function execStart(string $id, ?array $config = null, int $fetch = self::FETCH_RESPONSE): ?DockerResponse
    {
        $payload = $config ?? [
            'Detach' => false,
            'Tty' => false,
        ];

        $response = $this->requestJson('POST', "/exec/{$id}/start", [], $payload);
        $this->assertSuccess($response, 'Start exec');

        return $fetch === self::FETCH_RESPONSE ? $response : null;
    }

    public function execInspect(string $id): ?ExecIdJsonGetResponse200
    {
        $response = $this->request('GET', "/exec/{$id}/json");
        $this->assertSuccess($response, 'Inspect exec');

        return new ExecIdJsonGetResponse200($this->decodeResponse($response));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function containerLogs(string $id, array $params = [], int $fetch = self::FETCH_RESPONSE): ?DockerResponse
    {
        $response = $this->request('GET', "/containers/{$id}/logs", $params);
        $this->assertStatus($response, 'Container logs', [200, 404]);

        return $fetch === self::FETCH_RESPONSE ? $response : null;
    }

    public function containerInspect(string $id): ?ContainersIdJsonGetResponse200
    {
        $response = $this->request('GET', "/containers/{$id}/json");
        $this->assertSuccess($response, 'Inspect container');

        return new ContainersIdJsonGetResponse200($this->decodeResponse($response));
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $query
     */
    public function putContainerArchive(string $id, $handle, array $query = [], int $fetch = self::FETCH_RESPONSE): ?DockerResponse
    {
        $headers = [
            'Content-Type: application/x-tar',
        ];
        $response = $this->requestStream('PUT', "/containers/{$id}/archive", $handle, $query, $headers);
        $this->assertSuccess($response, 'Upload container archive');

        return $fetch === self::FETCH_RESPONSE ? $response : null;
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $headers
     */
    public function imageCreate(?string $name, array $query = [], array $headers = []): CreateImageStream
    {
        $response = $this->request('POST', '/images/create', $query, null, $headers);
        $this->assertSuccess($response, 'Create image');

        return new CreateImageStream($response->getBodyContents());
    }

    public function networkInspect(string $name): ?Network
    {
        $response = $this->request('GET', "/networks/{$name}");
        $this->assertSuccess($response, 'Inspect network');

        return new Network($this->decodeResponse($response));
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $headers
     */
    private function request(string $method, string $path, array $query = [], ?string $body = null, array $headers = []): DockerResponse
    {
        return $this->client->request($method, $path, $query, $body, $headers);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $payload
     */
    private function requestJson(string $method, string $path, array $query = [], array $payload = []): DockerResponse
    {
        $body = $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->request($method, $path, $query, $body);
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $headers
     * @param resource $handle
     */
    private function requestStream(string $method, string $path, $handle, array $query = [], array $headers = []): DockerResponse
    {
        return $this->client->requestStream($method, $path, $handle, $query, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(DockerResponse $response): array
    {
        return $response->getJson();
    }

    private function assertSuccess(DockerResponse $response, string $action): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }

        throw new DockerRequestException(
            $action . ' failed',
            $status,
            $response->getBodyContents()
        );
    }

    /**
     * @param array<int, int> $allowedStatuses
     */
    private function assertStatus(DockerResponse $response, string $action, array $allowedStatuses): void
    {
        $status = $response->getStatusCode();
        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        if ($status >= 200 && $status < 300) {
            return;
        }

        throw new DockerRequestException(
            $action . ' failed',
            $status,
            $response->getBodyContents()
        );
    }
}
