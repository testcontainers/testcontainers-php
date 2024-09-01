<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Docker\API\Exception\ContainerCreateNotFoundException;
use Docker\API\Model\ContainersCreatePostBody;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\HealthConfig;
use Docker\API\Model\HostConfig;
use Docker\API\Model\Mount;
use Docker\API\Model\PortBinding;
use Docker\Docker;
use Psr\Http\Message\ResponseInterface;
use Testcontainers\ContainerRuntime\ContainerRuntimeClient;
use Testcontainers\Wait\WaitForContainerRunning;
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

    protected bool $isPrivileged = false;
    protected ?string $networkName = null;

    /**
     * @var array<Mount>
     */
    protected array $mounts = [];

    /** @var array<string> List of exposed ports in the format ['8080/tcp'] */
    protected array $exposedPorts = [];

    public function __construct(string $image)
    {
        $this->image = $image;
        $this->dockerClient = ContainerRuntimeClient::getDockerClient();
    }

    /**
     * @deprecated Use constructor instead
     * Left for backward compatibility
     */
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

    /**
     * @deprecated Use `withExposedPorts` instead
     */
    public function withPort(string $localPort, string $containerPort): self
    {
        return $this->withExposedPorts($containerPort);
    }

    /**
     * @psalm-param string|int|array<string|int> $port
     */
    /**
     * Add ports to be exposed by the Docker container.
     * This method accepts multiple inputs: single port, multiple ports, or ports with specific protocols
     * to attempt to align with other language implementations.
     *
     * @psalm-param int|string|array<int|string> $ports One or more ports to expose.
     * @return self Fluent interface for chaining.
     */
    public function withExposedPorts(...$ports): self
    {
        foreach ($ports as $port) {
            if (is_array($port)) {
                // Flatten the array and recurse
                $this->withExposedPorts(...$port);
            } else {
                // Handle single port entry, either string or int
                $this->exposedPorts[] = $this->normalizePort($port);
            }
        }

        return $this;
    }

    /**
     * Normalize a port specification to ensure it includes a protocol.
     * Defaults to 'tcp' if no protocol is specified.
     *
     * @param string|int $port Port to normalize.
     * @return string Normalized port string.
     *
     * TODO: move this to a utility class
     */
    private function normalizePort(string|int $port): string
    {
        if (is_int($port)) {
            // Direct integer ports default to tcp
            return "{$port}/tcp";
        }

        // Check if the port specification already includes a protocol
        if (is_string($port) && !str_contains($port, '/')) {
            return "{$port}/tcp";
        }

        return $port;
    }

    public function withPrivileged(bool $privileged = true): self
    {
        $this->isPrivileged = $privileged;

        return $this;
    }

    public function withNetwork(string $networkName): self
    {
        $this->networkName = $networkName;

        return $this;
    }

    public function wait(): self
    {
        $this->wait->wait($this->id);
        return $this;
    }

    public function stop(): self
    {
        $this->dockerClient->containerStop($this->id);

        return $this;
    }

    public function start(): self
    {
        try {
            $containerCreatePostBody = new ContainersCreatePostBody();
            $portMap = new \ArrayObject();

            foreach ($this->exposedPorts as $port) {
                $portBinding = new PortBinding();
                $portBinding->setHostPort(explode('/', $port)[0]);
                $portBinding->setHostIp('0.0.0.0');
                $portMap[$port] = [$portBinding];
            }

            $hostConfig = new HostConfig();
            $hostConfig->setPortBindings($portMap);
            $containerCreatePostBody->setHostConfig($hostConfig);
            $containerCreatePostBody->setImage($this->image);
            $envs = [];
            foreach ($this->env as $key => $value) {
                $envs[] = $key . '=' . $value;
            }
            $containerCreatePostBody->setEnv($envs);

            $containerCreateResponse = $this->dockerClient->containerCreate($containerCreatePostBody);
            $this->id = $containerCreateResponse?->getId() ?? '';
        } catch (ContainerCreateNotFoundException) {
            $this->dockerClient->imageCreate(null, [
                'fromImage' => explode(':', $this->image)[0],
                'tag' => explode(':', $this->image)[1] ?? 'latest',
            ]);
            return $this->start();
        }

        $this->dockerClient->containerStart($this->id);

        if(!isset($this->wait)) {
            $this->withWait(new WaitForContainerRunning());
        }
        $this->wait();
        return $this;
    }

    public function restart(): self
    {
        $this->dockerClient->containerRestart($this->id);

        return $this;
    }

    public function remove(): self
    {
        $this->dockerClient->containerStop($this->id);
        $this->dockerClient->containerDelete($this->id);

        return $this;
    }

    public function kill(): self
    {
        $this->dockerClient->containerKill($this->id);

        return $this;
    }

    /**
     * @deprecated Use `start` instead
     * Left for backward compatibility
     */
    public function run(): self
    {
        return $this->start();
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
        $inspection = $this->inspect();
        return $inspection['gateway'];
        //        foreach ($containerNetworks as $network) {
        //            var_dump($network->getNetworkID(), $this->id, $network->getIPAddress());
        //            if($network->getNetworkID() === $this->id) {
        //                $containerAddress = $network->getIpAddress();
        //                break;
        //            }
        //        }
        //        return $containerAddress;
    }

    /**
     * @return array{gateway: string, ports: array<string, int>}
     */
    public function inspect(): array
    {
        $response = $this->dockerClient->containerInspect($this->id);
        $settings = $response->getNetworkSettings();
        //var_dump($settings);

        $ports = [];
        foreach ($settings->getPorts() as $port => $value) {
            if ($value === null) {
                continue;
            }

            $ports[$port] = (int) $value[0]->getHostPort();
        }

        return [
            'gateway' => $settings->getGateway(),
            'ports' => $ports,
        ];
    }
}
