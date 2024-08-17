<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Symfony\Component\Process\Process;
use Testcontainers\Exception\ContainerNotReadyException;
use Testcontainers\Registry;
use Testcontainers\Trait\DockerContainerAwareTrait;
use Testcontainers\Wait\WaitForNothing;
use Testcontainers\Wait\WaitInterface;

/**
 * @phpstan-type ContainerInspectSingleNetwork array<int, array{'NetworkSettings': array{'IPAddress': string}}>
 * @phpstan-type ContainerInspectMultipleNetworks array<int, array{'NetworkSettings': array{'Networks': array<string, array{'IPAddress': string}>}}>
 * @phpstan-type ContainerInspect ContainerInspectSingleNetwork|ContainerInspectMultipleNetworks
 * @phpstan-type DockerNetwork array{CreatedAt: string, Driver: string, ID: string, IPv6: string, Internal: string, Labels: string, Name: string, Scope: string}
 */
class Container
{
    use DockerContainerAwareTrait;

    private string $id;

    private ?string $entryPoint = null;

    /**
     * @var array<string, string>
     */
    private array $env = [];

    private Process $process;
    private WaitInterface $wait;

    private ?string $hostname = null;
    private bool $privileged = false;
    private ?string $network = null;
    private ?string $healthCheckCommand = null;
    private int $healthCheckIntervalInMS;

    /**
     * @var array<string>
     */
    private array $cmd = [];

    /**
     * @var ContainerInspect
     */
    private array $inspectedData;

    /**
     * @var array<string>
     */
    private array $mounts = [];

    /**
     * @var array<string>
     */
    private array $ports = [];

    protected function __construct(private string $image)
    {
        $this->wait = new WaitForNothing();
    }

    public static function make(string $image): self
    {
        return new Container($image);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function withHostname(string $hostname): self
    {
        $this->hostname = $hostname;

        return $this;
    }

    public function withEntryPoint(string $entryPoint): self
    {
        $this->entryPoint = $entryPoint;

        return $this;
    }

    public function withEnvironment(string $name, string $value): self
    {
        $this->env[$name] = $value;

        return $this;
    }

    public function withImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function withWait(WaitInterface $wait): self
    {
        $this->wait = $wait;

        return $this;
    }

    public function withHealthCheckCommand(string $command, int $healthCheckIntervalInMS = 1000): self
    {
        $this->healthCheckCommand = $command;
        $this->healthCheckIntervalInMS = $healthCheckIntervalInMS;

        return $this;
    }

    public function withCmd(array $cmd): self
    {
        $this->cmd = $cmd;

        return $this;
    }

    public function withMount(string $localPath, string $containerPath): self
    {
        $this->mounts[] = '-v';
        $this->mounts[] = sprintf('%s:%s', $localPath, $containerPath);

        return $this;
    }

    public function withPort(string $localPort, string $containerPort): self
    {
        $this->ports[] = '-p';
        $this->ports[] = sprintf('%s:%s', $localPort, $containerPort);

        return $this;
    }

    public function withPrivileged(bool $privileged = true): self
    {
        $this->privileged = $privileged;

        return $this;
    }

    public function withNetwork(string $network): self
    {
        $this->network = $network;

        return $this;
    }

    public function run(bool $wait = true): self
    {
        $this->id = uniqid('testcontainer', true);

        $params = [
            'docker',
            'run',
            '--rm',
            '--detach',
            '--name',
            $this->id,
            ...$this->mounts,
            ...$this->ports,
        ];

        foreach ($this->env as $name => $value) {
            $params[] = '--env';
            $params[] = $name . '=' . $value;
        }

        if ($this->healthCheckCommand !== null) {
            $params[] = '--health-cmd';
            $params[] = $this->healthCheckCommand;
            $params[] = '--health-interval';
            $params[] = $this->healthCheckIntervalInMS . 'ms';
        }

        if ($this->network !== null) {
            $params[] = '--network';
            $params[] = $this->network;
        }

        if ($this->hostname !== null) {
            $params[] = '--hostname';
            $params[] = $this->hostname;
        }

        if ($this->entryPoint !== null) {
            $params[] = '--entrypoint';
            $params[] = $this->entryPoint;
        }

        if ($this->privileged) {
            $params[] = '--privileged';
        }

        $params[] = $this->image;

        if (count($this->cmd) > 0) {
            array_push($params, ...$this->cmd);
        }

        $this->process = new Process($params);
        $this->process->mustRun();

        $this->inspectedData = self::dockerContainerInspect($this->id);

        Registry::add($this);

        if ($wait) {
            $this->wait();
        }

        return $this;
    }

    public function wait(int $wait = 100): self
    {
        for ($i = 0; $i < $wait; $i++) {
            try {
                $this->wait->wait($this->id);
                return $this;
            } catch (ContainerNotReadyException $e) {
                usleep(500000);
            }
        }

        throw new ContainerNotReadyException($this->id);
    }

    public function stop(): self
    {
        $stop = new Process(['docker', 'stop', $this->id]);
        $stop->mustRun();

        return $this;
    }

    public function start(): self
    {
        $start = new Process(['docker', 'start', $this->id]);
        $start->mustRun();

        return $this;
    }

    public function restart(): self
    {
        $restart = new Process(['docker', 'restart', $this->id]);
        $restart->mustRun();

        return $this;
    }

    public function remove(): self
    {
        $remove = new Process(['docker', 'rm', '-f', $this->id]);
        $remove->mustRun();

        Registry::remove($this);

        return $this;
    }

    public function kill(): self
    {
        $kill = new Process(['docker', 'kill', $this->id]);
        $kill->mustRun();

        return $this;
    }

    /**
     * @param array<string> $command
     */
    public function execute(array $command): Process
    {
        $process = new Process(['docker', 'exec', $this->id, ...$command]);
        $process->mustRun();

        return $process;
    }

    public function logs(): string
    {
        $logs = new Process(['docker', 'logs', $this->id]);
        $logs->mustRun();

        return $logs->getOutput();
    }

    public function getAddress(): string
    {
        return self::dockerContainerAddress(
            containerId: $this->id,
            networkName: $this->network,
            inspectedData: $this->inspectedData
        );
    }
}
