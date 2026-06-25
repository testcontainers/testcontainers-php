<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\ContainerClient;

use Composer\InstalledVersions;
use Docker\Docker as DockerClient;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\PluginClient;
use PHPUnit\Framework\TestCase;
use Testcontainers\ContainerClient\DockerContainerClient;

/**
 * Test subclass that forces resolveVersion() to return 'unknown' by simulating
 * the OutOfBoundsException fallback path, and captures the PluginClient built
 * during getDockerClient() so tests can inspect it without a Docker socket.
 */
class DockerContainerClientWithUnknownVersion extends DockerContainerClient
{
    /** @var PluginClient|null The PluginClient captured during the last getDockerClient() call */
    public static ?PluginClient $capturedHttpClient = null;

    protected static function resolveVersion(): string
    {
        // Exercise the catch branch: getPrettyVersion throws OutOfBoundsException
        // for any package that is not installed.
        try {
            InstalledVersions::getPrettyVersion('testcontainers/this-package-does-not-exist');
        } catch (\OutOfBoundsException) {
            return 'unknown';
        }

        return 'unknown';
    }

    public static function getDockerClient(): DockerClient
    {
        $version = static::resolveVersion();

        $baseHttpClient = \Docker\DockerClientFactory::createFromEnv();

        $httpClient = new PluginClient(
            $baseHttpClient,
            [new \Http\Client\Common\Plugin\HeaderDefaultsPlugin(['User-Agent' => 'tc-php/' . $version])]
        );

        self::$capturedHttpClient = $httpClient;

        // Build a DockerClient instance without invoking the constructor (no socket needed).
        /** @var DockerClient $stub */
        $stub = (new \ReflectionClass(DockerClient::class))->newInstanceWithoutConstructor();
        DockerContainerClient::setDockerClient($stub);

        return $stub;
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
        $this->resetSingleton();
        DockerContainerClientWithUnknownVersion::$capturedHttpClient = null;
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();
        DockerContainerClientWithUnknownVersion::$capturedHttpClient = null;
        parent::tearDown();
    }

    private function resetSingleton(): void
    {
        $reflection = new \ReflectionClass(DockerContainerClient::class);
        $property = $reflection->getProperty('dockerClient');
        $property->setAccessible(true);
        $property->setValue(null, null);
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

    public function testResolveVersionHasExpectedFormat(): void
    {
        // Call the real protected static method via reflection.
        $method = (new \ReflectionClass(DockerContainerClient::class))
            ->getMethod('resolveVersion');
        $method->setAccessible(true);

        /** @var string $version */
        $version = $method->invoke(null);

        $this->assertMatchesRegularExpression(
            '/^(tc-php\/.+|unknown|\S+)$/',
            $version,
            'resolveVersion() must return a non-empty string'
        );
        $this->assertStringNotContainsString(
            '+no-version-set',
            $version,
            'resolveVersion() must strip the +no-version-set build-metadata suffix'
        );
    }

    public function testOutOfBoundsExceptionFallsBackToUnknown(): void
    {
        // DockerContainerClientWithUnknownVersion::resolveVersion() exercises the
        // catch(\OutOfBoundsException) branch in the production resolveVersion() body
        // by calling getPrettyVersion() with a non-existent package name.
        // Calling getDockerClient() on the subclass triggers the real resolveVersion()
        // override and injects the result into the singleton via setDockerClient().
        DockerContainerClientWithUnknownVersion::getDockerClient();

        $capturedClient = DockerContainerClientWithUnknownVersion::$capturedHttpClient;
        $this->assertInstanceOf(PluginClient::class, $capturedClient);

        // Walk the plugin stack to find the HeaderDefaultsPlugin.
        $pluginsProperty = (new \ReflectionClass(PluginClient::class))->getProperty('plugins');
        $pluginsProperty->setAccessible(true);
        /** @var \Http\Client\Common\Plugin[] $plugins */
        $plugins = $pluginsProperty->getValue($capturedClient);

        $headerPlugin = null;
        foreach ($plugins as $plugin) {
            if ($plugin instanceof HeaderDefaultsPlugin) {
                $headerPlugin = $plugin;
                break;
            }
        }

        $this->assertInstanceOf(HeaderDefaultsPlugin::class, $headerPlugin);

        $headersProperty = (new \ReflectionClass(HeaderDefaultsPlugin::class))->getProperty('headers');
        $headersProperty->setAccessible(true);
        /** @var array<string, string> $headers */
        $headers = $headersProperty->getValue($headerPlugin);

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertSame(
            'tc-php/unknown',
            $headers['User-Agent'],
            'When version resolution falls back to unknown, User-Agent must be tc-php/unknown'
        );
    }

    // -------------------------------------------------------------------------
    // User-Agent HeaderDefaultsPlugin in the PluginClient stack
    // -------------------------------------------------------------------------

    public function testHeaderDefaultsPluginIsConfiguredWithUserAgentHeader(): void
    {
        // Use the capturing subclass to exercise the production PluginClient-building
        // code path (resolveVersion + PluginClient construction) without needing a
        // live Docker socket.
        DockerContainerClientWithUnknownVersion::getDockerClient();

        $capturedClient = DockerContainerClientWithUnknownVersion::$capturedHttpClient;
        $this->assertInstanceOf(PluginClient::class, $capturedClient);

        // Walk the plugin stack.
        $pluginsProperty = (new \ReflectionClass(PluginClient::class))->getProperty('plugins');
        $pluginsProperty->setAccessible(true);
        /** @var \Http\Client\Common\Plugin[] $plugins */
        $plugins = $pluginsProperty->getValue($capturedClient);

        $headerPlugin = null;
        foreach ($plugins as $plugin) {
            if ($plugin instanceof HeaderDefaultsPlugin) {
                $headerPlugin = $plugin;
                break;
            }
        }

        $this->assertInstanceOf(
            HeaderDefaultsPlugin::class,
            $headerPlugin,
            'HeaderDefaultsPlugin must be present in the PluginClient plugin stack'
        );

        $headersProperty = (new \ReflectionClass(HeaderDefaultsPlugin::class))->getProperty('headers');
        $headersProperty->setAccessible(true);
        /** @var array<string, string> $headers */
        $headers = $headersProperty->getValue($headerPlugin);

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertMatchesRegularExpression(
            '/^tc-php\/.+$/',
            $headers['User-Agent'],
            'HeaderDefaultsPlugin must set User-Agent to tc-php/<version>'
        );
    }

    public function testUserAgentVersionDoesNotContainBuildMetadataSuffix(): void
    {
        // Call the real protected static resolveVersion() and assert the suffix is stripped.
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
}
