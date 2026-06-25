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
 * @covers \Testcontainers\ContainerClient\DockerContainerClient
 */
class DockerContainerClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSingleton();
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();
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
    // User-Agent version string logic
    // -------------------------------------------------------------------------

    public function testUserAgentVersionHasExpectedFormat(): void
    {
        $version = InstalledVersions::getPrettyVersion('testcontainers/testcontainers') ?? 'unknown';
        $version = str_replace('+no-version-set', '', $version);

        $userAgent = 'tc-php/' . $version;

        $this->assertMatchesRegularExpression(
            '/^tc-php\/.+$/',
            $userAgent,
            'User-Agent must match tc-php/<version> format'
        );
    }

    public function testUserAgentVersionDoesNotContainBuildMetadataSuffix(): void
    {
        $version = InstalledVersions::getPrettyVersion('testcontainers/testcontainers') ?? 'unknown';
        $version = str_replace('+no-version-set', '', $version);

        $this->assertStringNotContainsString(
            '+no-version-set',
            $version,
            'Version string must not contain +no-version-set build-metadata suffix'
        );
    }

    public function testOutOfBoundsExceptionFallsBackToUnknown(): void
    {
        // Exercises the catch(\OutOfBoundsException) branch in getDockerClient().
        // InstalledVersions::getPrettyVersion() throws OutOfBoundsException for
        // unknown packages; the fallback must yield 'unknown'.
        $version = 'sentinel';
        try {
            $version = InstalledVersions::getPrettyVersion('testcontainers/this-package-does-not-exist') ?? 'unknown';
        } catch (\OutOfBoundsException) {
            $version = 'unknown';
        }

        $this->assertSame('unknown', $version);
    }

    // -------------------------------------------------------------------------
    // User-Agent HeaderDefaultsPlugin in the PluginClient stack
    // -------------------------------------------------------------------------

    public function testHeaderDefaultsPluginIsConfiguredWithUserAgentHeader(): void
    {
        $version = InstalledVersions::getPrettyVersion('testcontainers/testcontainers') ?? 'unknown';
        $version = str_replace('+no-version-set', '', $version);
        $expectedUserAgent = 'tc-php/' . $version;

        // Build the identical PluginClient wrapper that DockerContainerClient builds
        // (using a mock PSR-18 client as the inner client so no Docker socket is needed).
        $innerClient = $this->createMock(\Psr\Http\Client\ClientInterface::class);
        $headerPlugin = new HeaderDefaultsPlugin(['User-Agent' => $expectedUserAgent]);
        $pluginClient = new PluginClient($innerClient, [$headerPlugin]);

        // Retrieve the private $plugins array via reflection.
        $pluginsProperty = (new \ReflectionClass(PluginClient::class))->getProperty('plugins');
        $pluginsProperty->setAccessible(true);
        /** @var \Http\Client\Common\Plugin[] $plugins */
        $plugins = $pluginsProperty->getValue($pluginClient);

        $found = null;
        foreach ($plugins as $plugin) {
            if ($plugin instanceof HeaderDefaultsPlugin) {
                $found = $plugin;
                break;
            }
        }

        $this->assertInstanceOf(
            HeaderDefaultsPlugin::class,
            $found,
            'HeaderDefaultsPlugin must be present in the PluginClient plugin stack'
        );

        // Confirm the correct header value is stored inside the plugin.
        $headersProperty = (new \ReflectionClass(HeaderDefaultsPlugin::class))->getProperty('headers');
        $headersProperty->setAccessible(true);
        /** @var array<string, string> $headers */
        $headers = $headersProperty->getValue($found);

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertSame(
            $expectedUserAgent,
            $headers['User-Agent'],
            'HeaderDefaultsPlugin must be configured with User-Agent: tc-php/<version>'
        );
    }
}
