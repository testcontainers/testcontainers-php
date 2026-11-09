<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\Container;

use Docker\API\Model\ContainersCreatePostBody;
use Docker\Docker as DockerClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Container\GenericContainer;
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Utils\PortGenerator\FixedPortGenerator;
use Testcontainers\Utils\PortGenerator\PortGenerator;

/**
 * @covers \Testcontainers\Container\GenericContainer
 */
class GenericContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        (new \ReflectionClass(DockerContainerClient::class))->getProperty('dockerClient')->setValue(null, null);
        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidImageNames(): array
    {
        return [
            'uppercase repository' => ['MyImage:1.0', 'MyImage is not a valid Docker image name (in MyImage:1.0)'],
            'invalid tag' => ['nginx:-bad', '-bad is not a valid image versioning identifier (in nginx:-bad)'],
            'invalid registry port' => [
                'localhost:99999/app:1.0',
                'localhost:99999 is not a valid registry address (in localhost:99999/app:1.0)',
            ],
        ];
    }

    /**
     * The image name is validated before the Docker client is used, so no daemon is needed here.
     *
     * @dataProvider invalidImageNames
     */
    public function testRejectsInvalidImageName(string $image, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new GenericContainer($image);
    }

    /**
     * @return array<string, array{?PortGenerator, ?string}>
     */
    public static function portGenerators(): array
    {
        return [
            'docker allocates by default' => [null, null],
            'port generator' => [new FixedPortGenerator([8080]), '8080'],
        ];
    }

    /**
     * @dataProvider portGenerators
     */
    public function testHostPortOfExposedPort(?PortGenerator $portGenerator, ?string $hostPort): void
    {
        $created = null;
        $docker = $this->createMock(DockerClient::class);
        $docker->method('containerCreate')->willReturnCallback(
            static function (ContainersCreatePostBody $body) use (&$created): never {
                $created = $body;
                throw new RuntimeException('created');
            }
        );
        DockerContainerClient::setDockerClient($docker);
        $container = (new GenericContainer('nginx'))->withExposedPorts(80);
        if ($portGenerator !== null) {
            $container->withPortGenerator($portGenerator);
        }

        try {
            $container->start();
        } catch (RuntimeException) {
        }

        self::assertInstanceOf(ContainersCreatePostBody::class, $created);
        $binding = $created->getHostConfig()?->getPortBindings()['80/tcp'][0] ?? null;
        self::assertNotNull($binding);
        self::assertSame($hostPort, $binding->isInitialized('hostPort') ? $binding->getHostPort() : null);
    }
}
