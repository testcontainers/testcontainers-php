<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\Docker\Cli;

use PHPUnit\Framework\TestCase;
use Testcontainers\Docker\Cli\CliDockerClient;
use Testcontainers\Docker\Cli\CommandResult;
use Testcontainers\Docker\Cli\CommandRunnerInterface;
use Testcontainers\Docker\Exception\ContainerCreateNotFoundException;
use Testcontainers\Docker\Exception\DockerCommandException;
use Testcontainers\Docker\Model\ContainersCreatePostBody;
use Testcontainers\Docker\Model\ContainersIdExecPostBody;
use Testcontainers\Docker\Model\EndpointSettings;
use Testcontainers\Docker\Model\HealthConfig;
use Testcontainers\Docker\Model\HostConfig;
use Testcontainers\Docker\Model\Mount;
use Testcontainers\Docker\Model\NetworkingConfig;
use Testcontainers\Docker\Model\PortBinding;

/**
 * Records every command and replays scripted results.
 */
final class FakeCommandRunner implements CommandRunnerInterface
{
    /** @var list<array{command: list<string>, stdin: string|null}> */
    public array $calls = [];

    /** @var list<CommandResult> */
    private array $results;

    public function __construct(CommandResult ...$results)
    {
        $this->results = array_values($results);
    }

    public function run(array $command, $stdin = null): CommandResult
    {
        $input = null;
        if (is_resource($stdin)) {
            $input = (string) stream_get_contents($stdin);
        } elseif (is_string($stdin)) {
            $input = $stdin;
        }

        $this->calls[] = ['command' => $command, 'stdin' => $input];

        return array_shift($this->results) ?? new CommandResult(0, '', '');
    }
}

/**
 * @covers \Testcontainers\Docker\Cli\CliDockerClient
 */
class CliDockerClientTest extends TestCase
{
    public function testUsesConfiguredBinary(): void
    {
        $runner = new FakeCommandRunner();
        $client = new CliDockerClient('podman', $runner);

        $client->containerStart('abc');

        $this->assertSame([['podman', 'start', 'abc']], array_column($runner->calls, 'command'));
    }

    public function testCreateReadsBinaryFromEnvironment(): void
    {
        putenv('TESTCONTAINERS_CLI_BINARY=nerdctl');
        try {
            $this->assertSame('nerdctl', CliDockerClient::create()->getBinary());
        } finally {
            putenv('TESTCONTAINERS_CLI_BINARY');
        }

        $this->assertSame('docker', CliDockerClient::create()->getBinary());
    }

    public function testContainerCreateTranslatesTheApiPayloadIntoCliArguments(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(0, "abc123\n", ''));
        $client = new CliDockerClient('docker', $runner);

        $hostConfig = (new HostConfig())
            ->setPrivileged(true)
            ->setAutoRemove(true)
            ->setPortBindings(['8080/tcp' => [(new PortBinding())->setHostIp('0.0.0.0')->setHostPort('49152')]])
            ->setMounts([(new Mount())->setType('bind')->setSource('/host')->setTarget('/data')])
            ->setTmpfs(['/tmp' => 'rw,noexec']);

        $health = (new HealthConfig())
            ->setTest(['CMD-SHELL', 'curl -f localhost'])
            ->setInterval(1_000_000_000)
            ->setTimeout(3_000_000_000)
            ->setRetries(3);

        $endpoint = (new EndpointSettings())->setNetworkID('my-net')->setAliases(['db', 'primary']);
        $networking = (new NetworkingConfig())->setEndpointsConfig(['my-net' => $endpoint]);

        $body = (new ContainersCreatePostBody())
            ->setImage('alpine:3.14')
            ->setCmd(['tail', '-f', '/dev/null'])
            ->setLabels(['org.testcontainers' => 'true'])
            ->setHostname('box')
            ->setWorkingDir('/app')
            ->setUser('1000:1000')
            ->setEnv(['FOO=bar'])
            ->setExposedPorts(['8080/tcp' => new \stdClass(), '9000/udp' => new \stdClass()])
            ->setEntrypoint(['/bin/sh', '-c'])
            ->setHealthcheck($health)
            ->setNetworkingConfig($networking)
            ->setHostConfig($hostConfig);

        $response = $client->containerCreate($body, ['name' => 'my-container']);

        $this->assertSame('abc123', $response->getId());
        $this->assertSame([
            'docker', 'create',
            '--name', 'my-container',
            '--label', 'org.testcontainers=true',
            '--hostname', 'box',
            '--workdir', '/app',
            '--user', '1000:1000',
            '--env', 'FOO=bar',
            '--publish', '0.0.0.0:49152:8080/tcp',
            '--expose', '9000/udp',
            '--privileged',
            '--rm',
            '--mount', 'type=bind,source=/host,target=/data',
            '--tmpfs', '/tmp:rw,noexec',
            '--health-cmd', 'curl -f localhost',
            '--health-interval', '1000000000ns',
            '--health-timeout', '3000000000ns',
            '--health-retries', '3',
            '--network', 'my-net',
            '--network-alias', 'db',
            '--network-alias', 'primary',
            '--entrypoint', '/bin/sh',
            'alpine:3.14',
            '-c', 'tail', '-f', '/dev/null',
        ], $runner->calls[0]['command']);
    }

    public function testContainerCreateMapsMissingImageToNotFoundException(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(125, '', 'Error: manifest unknown: manifest unknown'));
        $client = new CliDockerClient('docker', $runner);

        $this->expectException(ContainerCreateNotFoundException::class);
        $client->containerCreate((new ContainersCreatePostBody())->setImage('nope:1'));
    }

    public function testFailedCommandThrowsWithStderr(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(1, '', 'permission denied'));
        $client = new CliDockerClient('docker', $runner);

        try {
            $client->containerStart('abc');
            $this->fail('Expected exception');
        } catch (DockerCommandException $e) {
            $this->assertSame(1, $e->getExitCode());
            $this->assertSame('permission denied', $e->getStderr());
            $this->assertSame(['docker', 'start', 'abc'], $e->getCommand());
            $this->assertStringContainsString('Start container failed', $e->getMessage());
        }
    }

    public function testExecLifecycleRunsCommandOnStartAndExposesExitCode(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(3, "out\n", "err\n"));
        $client = new CliDockerClient('docker', $runner);

        $exec = $client->containerExec('abc', (new ContainersIdExecPostBody())->setCmd(['sh', '-c', 'exit 3']));
        $execId = $exec->getId();
        $this->assertNotNull($execId);

        // Nothing runs until execStart(); exit code is unknown so far.
        $this->assertSame([], array_column($runner->calls, 'command'));
        $this->assertNull($client->execInspect($execId)->getExitCode());

        $response = $client->execStart($execId);

        $this->assertSame([['docker', 'exec', 'abc', 'sh', '-c', 'exit 3']], array_column($runner->calls, 'command'));
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame("out\nerr\n", $response->getBody()->getContents());
        $this->assertSame(3, $client->execInspect($execId)->getExitCode());
    }

    public function testExecStartWithUnknownIdFails(): void
    {
        $client = new CliDockerClient('docker', new FakeCommandRunner());

        $this->expectException(\RuntimeException::class);
        $client->execStart('unknown');
    }

    public function testContainerInspectDecodesFormattedJson(): void
    {
        $json = json_encode([
            'Name' => '/my-container',
            'State' => ['Status' => 'running'],
            'NetworkSettings' => ['Ports' => ['80/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '32768']]]],
        ], JSON_THROW_ON_ERROR);
        $runner = new FakeCommandRunner(new CommandResult(0, $json . "\n", ''));
        $client = new CliDockerClient('docker', $runner);

        $inspect = $client->containerInspect('abc');

        $this->assertSame(['docker', 'inspect', '--type', 'container', '--format', '{{json .}}', 'abc'], $runner->calls[0]['command']);
        $this->assertSame('my-container', trim($inspect->getName() ?? '', '/'));
        $this->assertSame('running', $inspect->getState()?->getStatus());
        $ports = $inspect->getNetworkSettings()?->getPorts();
        $this->assertNotNull($ports);
        $this->assertSame('32768', $ports['80/tcp'][0]->getHostPort());
    }

    public function testInspectUnwrapsSingleElementArrays(): void
    {
        $json = json_encode([['IPAM' => ['Config' => [['Gateway' => '172.17.0.1']]]]], JSON_THROW_ON_ERROR);
        $client = new CliDockerClient('docker', new FakeCommandRunner(new CommandResult(0, $json, '')));

        $network = $client->networkInspect('bridge');

        $this->assertSame('172.17.0.1', $network->getIPAM()?->getConfig()[0]->getGateway());
    }

    public function testPutContainerArchivePipesTarToCp(): void
    {
        $runner = new FakeCommandRunner();
        $client = new CliDockerClient('docker', $runner);

        $handle = fopen('php://memory', 'r+');
        $this->assertIsResource($handle);
        fwrite($handle, 'tar-bytes');
        rewind($handle);

        $client->putContainerArchive('abc', $handle, ['path' => '/']);
        fclose($handle);

        $this->assertSame(['docker', 'cp', '-', 'abc:/'], $runner->calls[0]['command']);
        $this->assertSame('tar-bytes', $runner->calls[0]['stdin']);
    }

    public function testImageCreatePullsImageWithTag(): void
    {
        $runner = new FakeCommandRunner();
        $client = new CliDockerClient('docker', $runner);

        $client->imageCreate(null, ['fromImage' => 'alpine', 'tag' => '3.14'], ['X-Registry-Auth: ignored'])->wait();

        $this->assertSame([['docker', 'pull', 'alpine:3.14']], array_column($runner->calls, 'command'));
    }

    public function testContainerDeleteIgnoresMissingContainers(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(1, '', 'Error response from daemon: No such container: abc'));
        $client = new CliDockerClient('docker', $runner);

        $client->containerDelete('abc');

        $this->assertCount(1, $runner->calls);
    }

    public function testContainerLogsCombineStdoutAndStderr(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(0, "hello\n", "warn\n"));
        $client = new CliDockerClient('docker', $runner);

        $response = $client->containerLogs('abc', ['stdout' => true, 'stderr' => true]);

        $this->assertSame(['docker', 'logs', 'abc'], $runner->calls[0]['command']);
        $this->assertSame("hello\nwarn\n", $response?->getBody()->getContents());
    }
}
