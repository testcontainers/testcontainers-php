<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\ContainerClient;

use Docker\Docker as DockerClient;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\PluginClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Testcontainers\ContainerClient\DockerContainerClient;

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
        // Delegates to the real production resolveVersion() with an unknown package.
        // The production catch(\OutOfBoundsException) block must catch the exception
        // and return 'unknown'. If that catch block is removed, an unhandled
        // OutOfBoundsException propagates and the test fails.
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
        $property->setAccessible(true);
        $property->setValue(null, null);

        DockerContainerClient::resetFactories();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Injects factory stubs so getDockerClient() runs its full production body
     * (version resolution → PluginClient wrapping → docker-client factory) without
     * opening a Docker socket. The injected $dockerClientFactory receives the real
     * PluginClient that production code constructs, providing a handle to inspect it.
     *
     * @param PluginClient|null $capturedHttpClient Out-param set to the PluginClient passed to the docker factory.
     */
    private function injectNoSocketFactories(?PluginClient &$capturedHttpClient = null): void
    {
        $mockPsrClient = $this->createMock(ClientInterface::class);

        DockerContainerClient::setFactories(
            static function () use ($mockPsrClient): ClientInterface {
                return $mockPsrClient;
            },
            static function (ClientInterface $httpClient) use (&$capturedHttpClient): DockerClient {
                // $httpClient here is the PluginClient wrapping the UA plugin — capture it.
                if (!$httpClient instanceof PluginClient) {
                    throw new \UnexpectedValueException(
                        'Expected PluginClient, got ' . get_debug_type($httpClient)
                    );
                }

                $capturedHttpClient = $httpClient;

                return (new \ReflectionClass(DockerClient::class))->newInstanceWithoutConstructor();
            }
        );
    }

    /**
     * Returns the private $plugins array from a PluginClient via reflection.
     *
     * @return \Http\Client\Common\Plugin[]
     */
    private function getPlugins(PluginClient $client): array
    {
        $prop = (new \ReflectionClass(PluginClient::class))->getProperty('plugins');
        $prop->setAccessible(true);

        /** @var \Http\Client\Common\Plugin[] $plugins */
        $plugins = $prop->getValue($client);

        return $plugins;
    }

    /**
     * Finds the first HeaderDefaultsPlugin in a PluginClient's plugin stack, or null.
     */
    private function findHeaderDefaultsPlugin(PluginClient $client): ?HeaderDefaultsPlugin
    {
        foreach ($this->getPlugins($client) as $plugin) {
            if ($plugin instanceof HeaderDefaultsPlugin) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * Returns the $headers array stored inside a HeaderDefaultsPlugin via reflection.
     *
     * @return array<string, string>
     */
    private function getHeadersFromPlugin(HeaderDefaultsPlugin $plugin): array
    {
        $prop = (new \ReflectionClass(HeaderDefaultsPlugin::class))->getProperty('headers');
        $prop->setAccessible(true);

        /** @var array<string, string> $headers */
        $headers = $prop->getValue($plugin);

        return $headers;
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
        $method->setAccessible(true);

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
        // Call the real production resolveVersion() with a package name that is not
        // installed. InstalledVersions::getPrettyVersion() throws OutOfBoundsException;
        // the production catch block converts that to 'unknown'.
        // If the catch block is removed from production code, this call throws an
        // unhandled OutOfBoundsException and the test fails — it is not self-testing.
        $method = (new \ReflectionClass(DockerContainerClient::class))
            ->getMethod('resolveVersion');
        $method->setAccessible(true);

        /** @var string $result */
        $result = $method->invoke(null, 'testcontainers/this-package-does-not-exist');

        $this->assertSame(
            'unknown',
            $result,
            'resolveVersion() must return "unknown" when the package is not found'
        );
    }

    // -------------------------------------------------------------------------
    // User-Agent HeaderDefaultsPlugin in the PluginClient stack
    // -------------------------------------------------------------------------

    public function testHeaderDefaultsPluginIsConfiguredWithUserAgentHeader(): void
    {
        // Inject no-socket factories so the real production getDockerClient() body runs:
        //   1. static::resolveVersion() — real production code, no change
        //   2. new PluginClient(..., [new HeaderDefaultsPlugin([...])]) — real production code
        //   3. self::createDockerClient($httpClient) — calls our injected factory, which
        //      captures the PluginClient and returns a no-constructor stub
        //
        // Removing HeaderDefaultsPlugin from getDockerClient() causes this test to fail
        // because $capturedHttpClient would then have no HeaderDefaultsPlugin in its stack.
        $capturedHttpClient = null;
        $this->injectNoSocketFactories($capturedHttpClient);

        DockerContainerClient::getDockerClient();

        $this->assertInstanceOf(
            PluginClient::class,
            $capturedHttpClient,
            'The docker client factory must receive a PluginClient'
        );

        $headerPlugin = $this->findHeaderDefaultsPlugin($capturedHttpClient);

        $this->assertInstanceOf(
            HeaderDefaultsPlugin::class,
            $headerPlugin,
            'HeaderDefaultsPlugin must be present in the PluginClient plugin stack'
        );

        $headers = $this->getHeadersFromPlugin($headerPlugin);

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertMatchesRegularExpression(
            '/^tc-php\/.+$/',
            $headers['User-Agent'],
            'HeaderDefaultsPlugin must set User-Agent to tc-php/<version>'
        );
    }

    public function testUserAgentHeaderContainsUnknownWhenVersionResolutionFails(): void
    {
        // Injects no-socket factories (same pattern as above), then calls
        // DockerContainerClientWithBrokenVersion::getDockerClient() which inherits the
        // production getDockerClient() body unchanged but overrides resolveVersion() to
        // pass a non-existent package to parent::resolveVersion(), triggering the real
        // OutOfBoundsException catch branch. The captured PluginClient must carry
        // User-Agent: tc-php/unknown.
        $capturedHttpClient = null;
        $mockPsrClient = $this->createMock(ClientInterface::class);

        DockerContainerClient::setFactories(
            static function () use ($mockPsrClient): ClientInterface {
                return $mockPsrClient;
            },
            static function (ClientInterface $httpClient) use (&$capturedHttpClient): DockerClient {
                if (!$httpClient instanceof PluginClient) {
                    throw new \UnexpectedValueException(
                        'Expected PluginClient, got ' . get_debug_type($httpClient)
                    );
                }

                $capturedHttpClient = $httpClient;

                return (new \ReflectionClass(DockerClient::class))->newInstanceWithoutConstructor();
            }
        );

        DockerContainerClientWithBrokenVersion::getDockerClient();

        $this->assertInstanceOf(PluginClient::class, $capturedHttpClient);

        $headerPlugin = $this->findHeaderDefaultsPlugin($capturedHttpClient);
        $this->assertInstanceOf(HeaderDefaultsPlugin::class, $headerPlugin);

        $headers = $this->getHeadersFromPlugin($headerPlugin);
        $this->assertSame(
            'tc-php/unknown',
            $headers['User-Agent'],
            'When version resolution falls back to unknown, User-Agent must be tc-php/unknown'
        );
    }
}
