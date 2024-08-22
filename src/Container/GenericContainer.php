<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Docker\API\Model\ContainersCreatePostBody;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\EndpointSettings;
use Docker\API\Model\HealthConfig;
use Docker\API\Model\Mount;
use Docker\API\Model\NetworkingConfig;
use Docker\API\Model\Port;
use Docker\Docker;
use Psr\Http\Message\ResponseInterface;
use Testcontainers\Exception\ContainerNotReadyException;
use Testcontainers\Registry;
use Testcontainers\Wait\WaitInterface;

/**
 * @phpstan-type ContainerInspectSingleNetwork array<int, array{'NetworkSettings': array{'IPAddress': string}}>
 * @phpstan-type ContainerInspectMultipleNetworks array<int, array{'NetworkSettings': array{'Networks': array<string, array{'IPAddress': string}>}}>
 * @phpstan-type ContainerInspect ContainerInspectSingleNetwork|ContainerInspectMultipleNetworks
 * @phpstan-type DockerNetwork array{CreatedAt: string, Driver: string, ID: string, IPv6: string, Internal: string, Labels: string, Name: string, Scope: string}
 */
class GenericContainer
{
    protected Docker $dockerClient;

    protected ContainersCreatePostBody $containerConfig;

    protected string $image;

    protected string $containerName;

    protected string $id;

    protected ?string $entryPoint = null;

    protected ?HealthConfig $healthConfig = null;

    /**
    * @var array<string, string>
    */
    protected array $env = [];

    protected WaitInterface $wait;

    protected bool $privileged = false;
    protected ?string $networkName = null;

    /**
     * @var array<Mount>
     */
    protected array $mounts = [];

    /**
     * @var array<Port>
     */
    protected array $ports = [];

    protected function __construct(string $image)
    {
        $this->image = $image;
        $this->dockerClient = Docker::create();
    }

    public static function make(string $image): self
    {
        return new GenericContainer($image);
    }

    public function getId(): string
    {
        return $this->id;
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
        $this->healthConfig = new HealthConfig([
            'Test' => ['CMD', $command],
            'Interval' => $healthCheckIntervalInMS,
        ]);

        return $this;
    }

    public function withMount(string $localPath, string $containerPath): self
    {
        $this->mounts[] = new Mount(['type' => 'bind', 'source' => $localPath, 'target' => $containerPath]);

        return $this;
    }

    public function withPort(string $localPort, string $containerPort): self
    {
        $this->ports[] = new Port(['privatePort' => (int) $containerPort, 'publicPort' => (int) $localPort]);

        return $this;
    }

    public function withPrivileged(bool $privileged = true): self
    {
        $this->privileged = $privileged;

        return $this;
    }

    public function withNetwork(string $networkName): self
    {
        $this->networkName = $networkName;

        return $this;
    }

    public function run(bool $wait = true): self
    {
        $this->containerName = uniqid('testcontainer', true);

        $this->containerConfig = new ContainersCreatePostBody();
        $this->containerConfig->setImage($this->image);

        $envs = [];
        foreach ($this->env as $name => $value) {
            $envs[] = $name . '=' . $value;
        }

        $this->containerConfig->setEnv($envs);

        if ($this->healthConfig !== null) {
            $this->containerConfig->setHealthcheck($this->healthConfig);
        }

        if ($this->networkName !== null) {
            $this->containerConfig->setNetworkingConfig(new NetworkingConfig([
                'endpointsConfig' => [
                    $this->networkName => new EndpointSettings([
                        'aliases' => [$this->containerName],
                        'networkID' => $this->networkName,
                    ]),
            ]]));
        }

        if ($this->entryPoint !== null) {
            $this->containerConfig->setEntrypoint([$this->entryPoint]);
        }

        if ($this->privileged) {
            //TODO: Implement privileged mode
        }

        $containerCreateResponse = $this->dockerClient->containerCreate($this->containerConfig, ['name' => $this->containerName]);

        $this->id = $containerCreateResponse->getId();

        Registry::add($this);

        if ($wait) {
            $this->wait();
        }

        return $this;
    }

    public function wait(int $wait = 100): self
    {
        usleep(500000);
        return $this;

//        for ($i = 0; $i < $wait; $i++) {
//            try {
//                $this->dockerClient->containerWait($this->id);
//                return $this;
//            } catch (ContainerNotReadyException $e) {
//                usleep(500000);
//            }
//        }
//
//        throw new ContainerNotReadyException($this->id);
    }

    public function stop(): self
    {
        $this->dockerClient->containerStop($this->id);

        return $this;
    }

    public function start(): self
    {
        $this->dockerClient->containerStart($this->id);

        return $this;
    }

    public function restart(): self
    {
        $this->dockerClient->containerRestart($this->id);

        return $this;
    }

    public function remove(): self
    {
        $this->dockerClient->containerDelete($this->id);

        Registry::remove($this);

        return $this;
    }

    public function kill(): self
    {
        $this->dockerClient->containerKill($this->id);

        return $this;
    }

    /**
     * @param array<string> $commandAsArray
     */
    public function execute(array $commandAsArray): ResponseInterface
    {
        $command = new ContainersIdExecPostBody();
        $command->setCmd($commandAsArray);
        return $this->dockerClient->containerExec($this->id, $command);
    }

    public function logs(): string
    {
        return $this->dockerClient->containerLogs($this->id)?->getBody()?->getContents() ?? '';
    }

    public function getAddress(): string
    {
        $containerNetworks = $this->dockerClient->containerInspect($this->id)
            ->getNetworkSettings()->getNetworks();
        $containerAddress = '';
        foreach ($containerNetworks as $network) {
            if($network->getNetworkID() === $this->id) {
                $containerAddress = $network->getIpAddress();
                break;
            }
        }
        return $containerAddress;
    }
}
