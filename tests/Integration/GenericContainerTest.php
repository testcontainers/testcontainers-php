<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Docker\API\Model\ContainersIdJsonGetResponse200;
use PHPUnit\Framework\TestCase;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForHostPort;
use Testcontainers\Wait\WaitForHttp;

class GenericContainerTest extends TestCase
{
    public function testExec(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();
        $result = $container->exec(['echo', 'testcontainers']);

        self::assertSame('testcontainers', $result);

        $container->stop();
    }

    public function testShouldCopyContentToContainer(): void
    {
        $inlineContent = 'hello world';

        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->withCopyContentToContainer([[
                'content' => $inlineContent,
                'target' => '/tmp/inline.txt',
            ]])
            ->start();

        $output = $container->exec(['cat', '/tmp/inline.txt']);

        self::assertSame($inlineContent, $output);

        $container->stop();
    }

    public function testShouldCopyDirectoryToContainer(): void
    {
        $testDir = sys_get_temp_dir() . '/copy-dir-test';
        if (!is_dir($testDir)) {
            mkdir($testDir);
        }
        file_put_contents($testDir . '/file1.txt', 'file1 contents');
        file_put_contents($testDir . '/file2.txt', 'file2 contents');

        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->withCopyDirectoriesToContainer([[
                'source' => $testDir,
                'target' => '/test-dir',
            ]])
            ->start();

        $output1 = $container->exec(['cat', '/test-dir/file1.txt']);
        $output2 = $container->exec(['cat', '/test-dir/file2.txt']);

        self::assertSame('file1 contents', $output1);
        self::assertSame('file2 contents', $output2);

        $container->stop();
    }

    public function testShouldCopyFileToContainer(): void
    {
        $localFilePath = sys_get_temp_dir() . '/copy-file-test.txt';
        file_put_contents($localFilePath, 'hello from file');

        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->withCopyFilesToContainer([[
                'source' => $localFilePath,
                'target' => '/tmp/test-file.txt',
            ]])
            ->start();

        $output = $container->exec(['cat', '/tmp/test-file.txt']);

        self::assertSame('hello from file', $output);

        $container->stop();
    }

    public function testShouldCopyFileWithPermissions(): void
    {
        $localFilePath = sys_get_temp_dir() . '/copy-perms-test.txt';
        file_put_contents($localFilePath, 'check perms');

        $mode = 0o777;

        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->withCopyFilesToContainer([[
                'source' => $localFilePath,
                'target' => '/tmp/perm-file.txt',
                'mode'   => $mode,
            ]])
            ->start();

        $output = $container->exec(['stat', '-c', '%a', '/tmp/perm-file.txt']);

        self::assertSame('777', trim($output));

        $container->stop();
    }

    public function testShouldReturnFirstMappedPortWithWaitForHostPort(): void
    {
        $container = (new GenericContainer('nginx'))
            ->withExposedPorts(80)
            ->withWait(new WaitForHostPort())
            ->start();
        $firstMappedPort = $container->getFirstMappedPort();

        self::assertSame($firstMappedPort, $container->getMappedPort(80));

        $container->stop();
    }

    public function testShouldReturnFirstMappedPortWithWaitForHttp(): void
    {
        $container = (new GenericContainer('nginx'))
            ->withExposedPorts(80)
            ->withWait((new WaitForHttp())->withMethod('GET')->withPath('/'))
            ->start();
        $firstMappedPort = $container->getFirstMappedPort();

        self::assertSame($firstMappedPort, $container->getMappedPort(80));

        $container->stop();
    }

    public function testShouldReturnMappedPortWithWaitForHttp(): void
    {
        $container = (new GenericContainer('nginx'))
            ->withExposedPorts(80)
            ->withWait((new WaitForHttp(80))->withMethod('GET')->withPath('/'))
            ->start();
        $mappedPort = $container->getMappedPort(80);

        self::assertSame($mappedPort, $container->getFirstMappedPort());

        $container->stop();
    }

    public function testShouldSetLabels(): void
    {
        $labels = [
            'label-1' => 'value-1',
            'label-2' => 'value-2',
        ];
        $container = (new GenericContainer('alpine'))
            ->withLabels($labels)
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        /** @var ContainersIdJsonGetResponse200|null $inspectResult */
        $inspectResult = $container->getClient()->containerInspect($container->getId());
        $this->assertArrayHasKey('label-1', (array)$inspectResult?->getConfig()?->getLabels());
        $this->assertSame('value-1', ((array)$inspectResult?->getConfig()?->getLabels())['label-1']);
        $this->assertArrayHasKey('label-2', (array)$inspectResult?->getConfig()?->getLabels());
        $this->assertSame('value-2', ((array)$inspectResult?->getConfig()?->getLabels())['label-2']);

        $container->stop();
    }

    public function testShouldSetName(): void
    {
        $name = 'test-container-name';
        $container = (new GenericContainer('alpine'))
            ->withName($name)
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        /** @var ContainersIdJsonGetResponse200|null $inspectResult */
        $inspectResult = $container->getClient()->containerInspect($container->getId());
        $this->assertSame('/'.$name, $inspectResult?->getName());

        $container->stop();
    }

    public function testShouldSetUser(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withUser('nobody')
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $output = $container->exec(['whoami']);
        $this->assertStringContainsString('nobody', $output);

        $container->stop();
    }

    public function testShouldSetWorkingDir(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withWorkingDir('/tmp')
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $output = $container->exec(['pwd']);
        $this->assertStringContainsString('/tmp', $output);

        $container->stop();
    }

    public function testShouldCaptureStderrWhenCommandFails(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();
        $result = $container->exec(['ls', '/nonexistent/path']);

        self::assertStringContainsString('No such file or directory', $result, 'Expected stderr in the output');

        $container->stop();
    }

    public function testShouldSetEnvironmentVariables(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->withEnvironment(['TEST_ENV' => 'testValue'])
            ->start();
        $output = $container->exec(['env']);

        self::assertStringContainsString('TEST_ENV=testValue', $output);

        $container->stop();
    }

    public function testShouldSetHealthCheckCommand(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withCommand(['tail', '-f', '/dev/null'])
            ->withHealthCheckCommand('echo "healthy" || exit 1')
            ->start();

        /** @var ContainersIdJsonGetResponse200|null $inspectResult */
        $inspectResult = $container->getClient()->containerInspect($container->getId());
        $healthConfig = $inspectResult?->getConfig()?->getHealthcheck();

        $this->assertNotNull($healthConfig);
        $this->assertEquals(['CMD-SHELL', 'echo "healthy" || exit 1'], $healthConfig->getTest());
        $this->assertSame(1000000000, $healthConfig->getInterval());
        $this->assertSame(3000000000, $healthConfig->getTimeout());
        $this->assertSame(3, $healthConfig->getRetries());

        $container->stop();
    }

    public function testShouldSetEntrypoint(): void
    {
        $container = (new GenericContainer('cristianrgreco/testcontainer:1.1.14'))
            ->withEntrypoint('node')
            ->withCommand(['index.js'])
            ->withExposedPorts(8080)
            ->start();

        /** @var ContainersIdJsonGetResponse200|null $inspectResult */
        $inspectResult = $container->getClient()->containerInspect($container->getId());
        $entrypoint = $inspectResult?->getConfig()?->getEntrypoint() ?? [];

        self::assertContains('node', $entrypoint);

        $container->stop();
    }

    public function testShouldSetMount(): void
    {
        $localPath = __DIR__ . '/../Fixtures/Docker';
        $containerPath = '/mnt/test-data';

        $container = (new GenericContainer('alpine'))
            ->withMount($localPath, $containerPath)
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $result = $container->exec(["cat", $containerPath.'/test.txt']);
        self::assertSame('hello world', $result);

        $container->stop();
    }

    public function testShouldSetTmpfs(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withTmpfs('/mnt/tmpfs')
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        $result = $container->exec(['stat', '-f', '-c', '%T', '/mnt/tmpfs']);
        self::assertSame('tmpfs', $result);

        $container->stop();
    }

    public function testShouldSetPrivilegedMode(): void
    {
        $container = (new GenericContainer('alpine'))
            ->withPrivilegedMode()
            ->withCommand(['tail', '-f', '/dev/null'])
            ->start();

        /** @var ContainersIdJsonGetResponse200|null $inspectResult */
        $inspectResult = $container->getClient()->containerInspect($container->getId());
        $privileged = $inspectResult?->getHostConfig()?->getPrivileged();

        self::assertTrue($privileged);

        $container->stop();
    }
}
