<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\Utils;

use Testcontainers\Docker\DockerClient;
use Testcontainers\Docker\Model\Network;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Utils\HostResolver;

class HostResolverTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('TESTCONTAINERS_HOST_OVERRIDE');
        putenv('DOCKER_HOST');
    }

    protected function tearDown(): void
    {
        putenv('TESTCONTAINERS_HOST_OVERRIDE');
        putenv('DOCKER_HOST');
    }

    public function testReturnsTestcontainersHostOverrideFromEnvironment(): void
    {
        // When the override is set, it should be returned.
        putenv('TESTCONTAINERS_HOST_OVERRIDE=tcp://another:2375');
        putenv('DOCKER_HOST=tcp://docker:2375');

        $dummyClient = $this->createMock(DockerClient::class);
        $resolver = new HostResolver($dummyClient);
        $host = $resolver->resolveHost();
        $this->assertEquals('tcp://another:2375', $host);
    }

    public function testReturnsHostnameForTcpProtocols(): void
    {
        $protocols = ['tcp', 'http', 'https'];
        foreach ($protocols as $protocol) {
            putenv('DOCKER_HOST=' . $protocol . '://docker:2375');
            // Clear any override.
            putenv('TESTCONTAINERS_HOST_OVERRIDE');
            $dummyClient = $this->createMock(DockerClient::class);
            $resolver = new HostResolver($dummyClient);
            $host = $resolver->resolveHost();
            $this->assertEquals('docker', $host, "Protocol {$protocol} did not return expected hostname.");
        }
    }

    public function testDoesNotReturnOverrideWhenAllowUserOverridesIsFalse(): void
    {
        $dummyClient = $this->createMock(DockerClient::class);
        $resolver = new class ($dummyClient) extends HostResolver {
            protected function allowUserOverrides(): bool
            {
                return false;
            }
        };

        putenv('TESTCONTAINERS_HOST_OVERRIDE=tcp://another:2375');
        putenv('DOCKER_HOST=tcp://docker:2375');
        $host = $resolver->resolveHost();
        $this->assertEquals('docker', $host);
    }

    public function testReturnsLocalhostForUnixAndNpipeProtocolsWhenNotInContainer(): void
    {
        $dummyClient = $this->createMock(DockerClient::class);
        $resolver = new class ($dummyClient) extends HostResolver {
            protected function isInContainer(): bool
            {
                return false;
            }
        };

        foreach (['unix://docker:2375', 'npipe://docker:2375'] as $uri) {
            putenv('DOCKER_HOST=' . $uri);
            putenv('TESTCONTAINERS_HOST_OVERRIDE');
            $host = $resolver->resolveHost();
            $this->assertEquals('localhost', $host, "URI {$uri} should return 'localhost' when not in a container.");
        }
    }

    public function testReturnsHostFromGatewayWhenRunningInContainer(): void
    {
        // For this test we simulate that we are in a container and the Docker client returns a gateway.
        $dockerClient = $this->getMockBuilder(DockerClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $fakeNetwork = new Network([
            'IPAM' => [
                'Config' => [
                    ['Gateway' => '172.0.0.1'],
                ],
            ],
        ]);

        // Expect that networkInspect will be called with "bridge" (since DOCKER_HOST does not contain "podman.sock")
        $dockerClient->expects($this->once())
            ->method('networkInspect')
            ->with($this->equalTo('bridge'))
            ->willReturn($fakeNetwork);

        // Override isInContainer() to simulate being inside a container.
        $resolver = new class ($dockerClient) extends HostResolver {
            protected function isInContainer(): bool
            {
                return true;
            }
        };

        putenv('DOCKER_HOST=unix://docker:2375');
        putenv('TESTCONTAINERS_HOST_OVERRIDE');
        $host = $resolver->resolveHost();
        $this->assertEquals('172.0.0.1', $host);
    }

    public function testUsesBridgeNetworkAsGatewayForDockerProvider(): void
    {
        // For Docker provider (non-Podman) the network used should be "bridge".
        $dockerClient = $this->getMockBuilder(DockerClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        // Expect networkInspect to be called with "bridge"
        $dockerClient->expects($this->once())
            ->method('networkInspect')
            ->with($this->equalTo('bridge'))
            ->willReturn(null); // Simulate not finding a gateway

        $resolver = new class ($dockerClient) extends HostResolver {
            protected function isInContainer(): bool
            {
                return true;
            }

            protected function findDefaultGateway(): ?string
            {
                return null;
            }
        };

        putenv('DOCKER_HOST=unix://docker:2375');
        $host = $resolver->resolveHost();
        // Since no gateway is found, fallback is "localhost"
        $this->assertEquals('localhost', $host);
    }

    public function testUsesPodmanNetworkAsGatewayForPodmanProvider(): void
    {
        // For Podman, DOCKER_HOST contains "podman.sock" so the network should be "podman".
        $dockerClient = $this->getMockBuilder(DockerClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        // Expect networkInspect to be called with "podman"
        $dockerClient->expects($this->once())
            ->method('networkInspect')
            ->with($this->equalTo('podman'))
            ->willReturn(null); // Simulate not finding a gateway

        $resolver = new class ($dockerClient) extends HostResolver {
            protected function isInContainer(): bool
            {
                return true;
            }

            protected function findDefaultGateway(): ?string
            {
                return null;
            }
        };

        putenv('DOCKER_HOST=unix://podman.sock');
        $host = $resolver->resolveHost();
        $this->assertEquals('localhost', $host);
    }

    public function testReturnsHostFromDefaultGatewayWhenRunningInContainer(): void
    {
        // Override both findGateway() and findDefaultGateway() to simulate a missing network gateway and a default gateway result.
        $dummyClient = $this->createMock(DockerClient::class);
        $resolver = new class ($dummyClient) extends HostResolver {
            protected function isInContainer(): bool
            {
                return true;
            }
            protected function findGateway(string $networkName): ?string
            {
                return null;
            }
            protected function findDefaultGateway(): ?string
            {
                return '172.0.0.2';
            }
        };

        putenv('DOCKER_HOST=unix://docker:2375');
        $host = $resolver->resolveHost();
        $this->assertEquals('172.0.0.2', $host);
    }

    public function testReturnsLocalhostIfUnableToFindGateway(): void
    {
        // Override to simulate that neither network inspection nor default gateway yield a result.
        $dummyClient = $this->createMock(DockerClient::class);
        $resolver = new class ($dummyClient) extends HostResolver {
            protected function isInContainer(): bool
            {
                return true;
            }
            protected function findGateway(string $networkName): ?string
            {
                return null;
            }
            protected function findDefaultGateway(): ?string
            {
                return null;
            }
        };

        putenv('DOCKER_HOST=unix://docker:2375');
        $host = $resolver->resolveHost();
        $this->assertEquals('localhost', $host);
    }

    public function testThrowsForUnsupportedProtocol(): void
    {
        putenv('DOCKER_HOST=invalid://unknown');
        $dummyClient = $this->createMock(DockerClient::class);
        $resolver = new HostResolver($dummyClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unsupported Docker host scheme: invalid");

        $resolver->resolveHost();
    }
}
