<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\ContainerClient;

use PHPUnit\Framework\TestCase;
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Docker\Cli\CliDockerClient;
use Testcontainers\Docker\Client\ClientInterface;
use Testcontainers\Docker\DockerClient;

/**
 * Test subclass used by testUserAgentHeaderContainsUnknownWhenVersionResolutionFails.
 *
 * Overrides resolveVersion() to pass a non-existent package name to the parent
 * implementation, exercising the OutOfBoundsException catch branch in the
 * production resolveVersion() body. getDockerClient() is inherited unmodified
 * from DockerContainerClient — the only change is the version source.
 */
class DockerContainerClientWithBrokenVersion extends DockerContainerClient
{
    protected static function resolveVersion(string $package = 'testcontainers/testcontainers'): string
    {
        return parent::resolveVersion('testcontainers/this-package-does-not-exist');
    }
}

/**
 * @covers \Testcontainers\ContainerClient\DockerContainerClient
 */
class DockerContainerClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetState();
    }

    protected function tearDown(): void
    {
        $this->resetState();
        parent::tearDown();
    }

    private function resetState(): void
    {
        $reflection = new \ReflectionClass(DockerContainerClient::class);
        $property = $reflection->getProperty('dockerClient');
        $property->setValue(null, null);

        DockerContainerClient::resetDockerClientFactory();
        putenv('TESTCONTAINERS_CLIENT');
        putenv('TESTCONTAINERS_CLI_BINARY');
    }

    /**
     * Injects a factory so getDockerClient() runs its full production body
     * (version resolution → User-Agent construction) without opening a Docker socket.
     * The factory captures the User-Agent string production code passes to it.
     */
    private function injectCapturingFactory(?string &$capturedUserAgent): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        DockerContainerClient::setDockerClientFactory(
            static function (string $userAgent) use (&$capturedUserAgent, $httpClient): DockerClient {
                $capturedUserAgent = $userAgent;

                return new DockerClient($httpClient);
            }
        );
    }

    // -------------------------------------------------------------------------
    // Singleton behaviour
    // -------------------------------------------------------------------------

    public function testSetDockerClientOverridesSingleton(): void
    {
        $mock = $this->getMockBuilder(DockerClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        DockerContainerClient::setDockerClient($mock);

        $this->assertSame($mock, DockerContainerClient::getDockerClient());
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $mock = $this->getMockBuilder(DockerClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        DockerContainerClient::setDockerClient($mock);

        $first = DockerContainerClient::getDockerClient();
        $second = DockerContainerClient::getDockerClient();

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // resolveVersion() — exercises production version-resolution logic
    // -------------------------------------------------------------------------

    public function testUserAgentVersionDoesNotContainBuildMetadataSuffix(): void
    {
        $method = (new \ReflectionClass(DockerContainerClient::class))
            ->getMethod('resolveVersion');

        /** @var string $version */
        $version = $method->invoke(null);

        $this->assertStringNotContainsString(
            '+no-version-set',
            $version,
            'resolveVersion() must strip the +no-version-set build-metadata suffix'
        );
    }

    public function testOutOfBoundsExceptionFallsBackToUnknown(): void
    {
        $method = (new \ReflectionClass(DockerContainerClient::class))
            ->getMethod('resolveVersion');

        /** @var string $result */
        $result = $method->invoke(null, 'testcontainers/this-package-does-not-exist');

        $this->assertSame(
            'unknown',
            $result,
            'resolveVersion() must return "unknown" when the package is not found'
        );
    }

    // -------------------------------------------------------------------------
    // User-Agent passed to the Docker client
    // -------------------------------------------------------------------------

    public function testDockerClientIsCreatedWithUserAgentHeader(): void
    {
        $capturedUserAgent = null;
        $this->injectCapturingFactory($capturedUserAgent);

        DockerContainerClient::getDockerClient();

        $this->assertIsString($capturedUserAgent);
        $this->assertMatchesRegularExpression(
            '/^tc-php\/.+$/',
            $capturedUserAgent,
            'DockerClient must be created with User-Agent tc-php/<version>'
        );
    }

    public function testUserAgentHeaderContainsUnknownWhenVersionResolutionFails(): void
    {
        $capturedUserAgent = null;
        $this->injectCapturingFactory($capturedUserAgent);

        DockerContainerClientWithBrokenVersion::getDockerClient();

        $this->assertSame(
            'tc-php/unknown',
            $capturedUserAgent,
            'When version resolution falls back to unknown, User-Agent must be tc-php/unknown'
        );
    }

    // -------------------------------------------------------------------------
    // Adapter selection via TESTCONTAINERS_CLIENT
    // -------------------------------------------------------------------------

    public function testCliAdapterIsSelectedFromEnvironment(): void
    {
        putenv('TESTCONTAINERS_CLIENT=cli');
        putenv('TESTCONTAINERS_CLI_BINARY=podman');

        $client = DockerContainerClient::getDockerClient();

        $this->assertInstanceOf(CliDockerClient::class, $client);
        $this->assertSame('podman', $client->getBinary());
    }

    public function testUnknownAdapterIsRejected(): void
    {
        putenv('TESTCONTAINERS_CLIENT=carrier-pigeon');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('carrier-pigeon');
        DockerContainerClient::getDockerClient();
    }
}
