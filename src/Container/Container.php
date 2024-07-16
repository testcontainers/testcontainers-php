<?php

declare(strict_types=1);

namespace Testcontainer\Container;

use Symfony\Component\Process\Process;
use Testcontainer\Exception\ContainerNotReadyException;
use Testcontainer\Registry;
use Testcontainer\Wait\WaitForNothing;
use Testcontainer\Wait\WaitInterface;

/**
 * @phpstan-type ContainerInspect array{0: array{NetworkSettings: array{IPAddress: string}}}
 */
class Container
{
    private string $id;

    private ?string $entryPoint = null;

    /**
    * @var array<string, string>
    */
    private array $env = [];

    private Process $process;
    private WaitInterface $wait;

    private bool $privileged = false;
    private ?string $network = null;
    private ?string $healthCheckCommand = null;
    private int $healthCheckIntervalInMS;

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

        if ($this->entryPoint !== null) {
            $params[] = '--entrypoint';
            $params[] = $this->entryPoint;
        }

        if ($this->privileged) {
            $params[] = '--privileged';
        }

        $params[] = $this->image;

        $this->process = new Process($params);
        $this->process->mustRun();

        $inspect = new Process(['docker', 'inspect', $this->id]);
        $inspect->mustRun();

        /** @var ContainerInspect $inspectedData  */
        $inspectedData = json_decode($inspect->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->inspectedData = $inspectedData;

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
        if ($this->network !== null && !empty($this->inspectedData[0]['NetworkSettings']['Networks'][$this->network]['IPAddress'])) {
            return $this->inspectedData[0]['NetworkSettings']['Networks'][$this->network]['IPAddress'];
        }

        return $this->inspectedData[0]['NetworkSettings']['IPAddress'];
    }
}
