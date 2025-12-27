<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\Docker;

use PHPUnit\Framework\TestCase;
use Testcontainers\Docker\Client\ClientInterface;
use Testcontainers\Docker\DockerClient;
use Testcontainers\Docker\DockerResponse;
use Testcontainers\Docker\Model\ContainersCreatePostBody;

class DockerClientTest extends TestCase
{
    public function testContainerCreate(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('POST', '/containers/create', [], json_encode(['Image' => 'alpine:latest'], JSON_THROW_ON_ERROR))
            ->willReturn(new DockerResponse(201, json_encode(['Id' => '123'], JSON_THROW_ON_ERROR)));

        $dockerClient = new DockerClient($client);
        $postBody = new ContainersCreatePostBody();
        $postBody->setImage('alpine:latest');
        $response = $dockerClient->containerCreate($postBody);

        $this->assertNotNull($response);
        $this->assertSame('123', $response->getId());
    }

    public function testContainerStart(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('POST', '/containers/123/start')
            ->willReturn(new DockerResponse(204, ''));

        $dockerClient = new DockerClient($client);
        $dockerClient->containerStart('123');
    }
}
