<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Testcontainers\Container\GenericContainer;

class StartedGenericContainerTest extends ContainerTestCase
{
    public function testShouldReturnContainerId(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $this->container = $container;

        self::assertNotEmpty($container->getId(), 'Container ID should not be empty');
    }

    public function testShouldReturnLastExecId(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $this->container = $container;

        $container->exec(['echo', 'Test Exec ID']);

        $lastExecId = $container->getLastExecId();

        self::assertNotNull($lastExecId, 'Last exec ID should not be null');
        self::assertNotEmpty($lastExecId, 'Last exec ID should not be empty');
        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', $lastExecId, 'Last exec ID should be a valid hexadecimal string');
    }

    public function testShouldExecuteCommandInContainer(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $this->container = $container;

        $output = $container->exec(['echo', 'Hello, Testcontainers!']);
        self::assertSame('Hello, Testcontainers!', $output);
    }

    public function testShouldStopContainer(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        self::assertNotEmpty($container->getId(), 'Container ID should not be empty');

        $stoppedContainer = $container->stop();

        self::assertSame(
            $container->getId(),
            $stoppedContainer->getId(),
            'Stopped container ID should match the original container ID'
        );

        self::assertStringContainsString(
            'No such container',
            $container->logs(),
            'Expected message indicating container does not exist'
        );
    }

    public function testShouldRestartContainer(): void
    {
        $container = (new GenericContainer('nginx'))
            ->withExposedPorts(80)
            ->start();

        $this->container = $container;

        $containerIdBeforeRestart = $container->getId();
        $container->restart();
        $containerIdAfterRestart = $container->getId();

        self::assertSame(
            $containerIdBeforeRestart,
            $containerIdAfterRestart,
            'Container ID should remain the same after restart'
        );
    }

    public function testShouldRetrieveLogs(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['sh', '-c', 'echo "Hello from logs!" && tail -f /dev/null'])
            ->start();

        $this->container = $container;

        $logs = $container->logs();
        self::assertStringContainsString('Hello from logs!', $logs);
    }

    public function testShouldRetrieveHost(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $this->container = $container;

        $host = $container->getHost();
        self::assertSame('127.0.0.1', $host, 'Host should be 127.0.0.1');
    }

    public function testShouldRetrieveFirstMappedPort(): void
    {
        $container = (new GenericContainer('nginx'))
            ->withExposedPorts(80)
            ->start();

        $this->container = $container;

        $mappedPort = $container->getFirstMappedPort();
        self::assertGreaterThan(0, $mappedPort, 'Mapped port should be greater than 0');
    }

    public function testShouldRetrieveMappedPort(): void
    {
        $container = (new GenericContainer('nginx'))
            ->withExposedPorts(80)
            ->start();

        $this->container = $container;

        $mappedPort = $container->getMappedPort(80);
        self::assertGreaterThan(0, $mappedPort, 'Mapped port for 80 should be greater than 0');
    }

    public function testShouldRetrieveContainerName(): void
    {
        $name = 'test-container-name';
        $container = (new GenericContainer('alpine'))
            ->withName($name)
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $this->container = $container;

        self::assertSame($name, $container->getName(), 'Container name should match');
    }

    public function testShouldRetrieveLabels(): void
    {
        $labels = [
            'label-1' => 'value-1',
            'label-2' => 'value-2',
        ];

        $container = (new GenericContainer('alpine'))
            ->withLabels($labels)
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $this->container = $container;

        $retrievedLabels = $container->getLabels();

        self::assertArrayHasKey('label-1', $retrievedLabels);
        self::assertSame('value-1', $retrievedLabels['label-1']);
        self::assertArrayHasKey('label-2', $retrievedLabels);
        self::assertSame('value-2', $retrievedLabels['label-2']);
    }

    public function testShouldRetrieveNetworkNames(): void
    {
        $container = (new GenericContainer('nginx'))
            ->withExposedPorts(80)
            ->start();

        $this->container = $container;

        $networks = $container->getNetworkNames();

        self::assertNotEmpty($networks, 'Networks should not be empty');
    }

    public function testShouldRetrieveIpAddressFromNetwork(): void
    {
        $container = (new GenericContainer('nginx'))
            ->withExposedPorts(80)
            ->start();

        $this->container = $container;

        $networks = $container->getNetworkNames();
        $networkName = $networks[0] ?? null;

        self::assertNotNull($networkName, 'Network name should not be null');

        $ipAddress = $container->getIpAddress($networkName);

        self::assertNotEmpty($ipAddress, 'IP address should not be empty');
    }
}
