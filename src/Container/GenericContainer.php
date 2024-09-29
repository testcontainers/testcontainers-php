<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Docker\API\Exception\ContainerCreateNotFoundException;
use Docker\API\Model\ContainerCreateResponse;
use Docker\API\Model\ContainersCreatePostBody;
use Docker\API\Model\EndpointSettings;
use Docker\API\Model\HealthConfig;
use Docker\API\Model\HostConfig;
use Docker\API\Model\Mount;
use Docker\API\Model\NetworkingConfig;
use Docker\API\Model\PortBinding;
use Docker\Docker;
use Docker\Stream\CreateImageStream;
use InvalidArgumentException;
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Utils\PortGenerator\RandomUniquePortGenerator;
use Testcontainers\Utils\PortNormalizer;
use Testcontainers\Wait\WaitForContainer;
use Testcontainers\Wait\WaitStrategy;

class GenericContainer implements TestContainer
{
    protected Docker $dockerClient;

    protected string $image;

    protected string $id;

    /** @var list<string> */
    protected array $command = [];

    protected ?string $entryPoint = null;

    protected ?HealthConfig $healthConfig = null;

    /**
    * @var array<string, string>
    */
    protected array $env = [];

    protected WaitStrategy $waitStrategy;

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
        $this->dockerClient = DockerContainerClient::getDockerClient();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param list<string> $command
     */
    public function withCommand(array $command): static
    {
        $this->command = $command;

        return $this;
    }

    public function withEntryPoint(string $entryPoint): static
    {
        $this->entryPoint = $entryPoint;

        return $this;
    }

    /**
     * To support temporarily backwards compatibility, the method supports two formats:
     * 1. A single key-value pair (deprecated): $object->withEnvironment('key', 'value');
     * 2. An array of key-value pairs: $object->withEnvironment(['key1' => 'value1', 'key2' => 'value2']);
     *
     * @param string | array<string, string> $env An array of environment variables or the name of a single variable.
     * @param string|null $value The value of the environment variable if a single variable is passed.
     * @return static Returns itself for chaining purposes.
     */
    public function withEnvironment(string | array $env, ?string $value = null): static
    {
        if (is_array($env)) {
            foreach ($env as $key => $val) {
                $this->env[$key] = $val;
            }
        } else {
            if ($value === null) {
                throw new InvalidArgumentException("Value cannot be null when setting a single environment variable.");
            }
            $this->env[$env] = $value;
        }

        return $this;
    }

    public function withWait(WaitStrategy $waitStrategy): static
    {
        $this->waitStrategy = $waitStrategy;

        return $this;
    }

    public function withHealthCheckCommand(string $command, int $healthCheckIntervalInMS = 1000): static
    {
        $this->healthConfig = new HealthConfig([
            'Test' => ['CMD', $command],
            'Interval' => $healthCheckIntervalInMS,
        ]);

        return $this;
    }

    public function withMount(string $localPath, string $containerPath): static
    {
        $this->mounts[] = new Mount(['type' => 'bind', 'source' => $localPath, 'target' => $containerPath]);

        return $this;
    }

    /**
     * Add ports to be exposed by the Docker container.
     * This method accepts multiple inputs: single port, multiple ports, or ports with specific protocols
     * to attempt to align with other language implementations.
     *
     * @psalm-param int|string|array<int|string> $ports One or more ports to expose.
     * @return static Fluent interface for chaining.
     */
    public function withExposedPorts(...$ports): static
    {
        foreach ($ports as $port) {
            if (is_array($port)) {
                // Flatten the array and recurse
                $this->withExposedPorts(...$port);
            } else {
                // Handle single port entry, either string or int
                $this->exposedPorts[] = PortNormalizer::normalizePort($port);
            }
        }

        return $this;
    }

    public function withPrivilegedMode(bool $privileged = true): static
    {
        $this->isPrivileged = $privileged;

        return $this;
    }

    //TODO: not yet implemented
    public function withNetwork(string $networkName): static
    {
        $this->networkName = $networkName;

        return $this;
    }

    public function start(): StartedGenericContainer
    {
        $containerConfig = $this->createContainerConfig();
        try {
            /** @var ContainerCreateResponse|null $containerCreateResponse */
            $containerCreateResponse = $this->dockerClient->containerCreate($containerConfig);
            $this->id = $containerCreateResponse?->getId() ?? '';
        } catch (ContainerCreateNotFoundException) {
            // If the image is not found, pull it and try again
            $this->pullImage();
            return $this->start();
        }

        $this->dockerClient->containerStart($this->id);

        if (!isset($this->waitStrategy)) {
            $this->withWait(new WaitForContainer());
        }

        $startedContainer = new StartedGenericContainer($this->id);
        $this->waitStrategy->wait($startedContainer);

        return $startedContainer;
    }

    protected function createContainerConfig(): ContainersCreatePostBody
    {
        $containerCreatePostBody = new ContainersCreatePostBody();
        $containerCreatePostBody->setImage($this->image);
        $containerCreatePostBody->setCmd($this->command);

        $envs = array_map(static fn ($key, $value) => "$key=$value", array_keys($this->env), $this->env);
        $containerCreatePostBody->setEnv($envs);

        $hostConfig = $this->createHostConfig();
        $containerCreatePostBody->setHostConfig($hostConfig);

        if ($this->entryPoint !== null) {
            $containerCreatePostBody->setEntrypoint([$this->entryPoint]);
        }

        if ($this->healthConfig !== null) {
            $containerCreatePostBody->setHealthcheck($this->healthConfig);
        }

        if ($this->networkName !== null) {
            $networkingConfig = new NetworkingConfig();
            $endpointsConfig = new \ArrayObject([
                $this->networkName => new EndpointSettings(),
            ]);
            $networkingConfig->setEndpointsConfig($endpointsConfig);
            $containerCreatePostBody->setNetworkingConfig($networkingConfig);
        }

        return $containerCreatePostBody;
    }

    protected function createHostConfig(): ?HostConfig
    {
        /**
         * For some reason, if some of the properties are not set, but HostConfig is returned,
         * the API will throw ContainerCreateBadRequestException: bad parameter.
         * Until it will be checked and fixed, we just return null if these properties are not set.
         * */
        if ($this->exposedPorts === [] && !$this->isPrivileged && $this->mounts === []) {
            return null;
        }

        $hostConfig = new HostConfig();

        if ($this->exposedPorts !== []) {
            $portBindings = $this->createPortBindings();
            $hostConfig->setPortBindings($portBindings);
        }

        if ($this->isPrivileged) {
            $hostConfig->setPrivileged(true);
        }

        if ($this->mounts !== []) {
            $hostConfig->setMounts($this->mounts);
        }

        return $hostConfig;
    }

    /**
     * @return \ArrayObject<string, list<PortBinding>>
     */
    protected function createPortBindings(): \ArrayObject
    {
        $portGenerator = new RandomUniquePortGenerator();
        $portBindings = new \ArrayObject();

        foreach ($this->exposedPorts as $port) {
            $portBinding = new PortBinding();
            $portBinding->setHostPort((string)$portGenerator->generatePort());
            $portBinding->setHostIp('0.0.0.0');
            $portBindings[$port] = [$portBinding];
        }

        return $portBindings;
    }

    protected function pullImage(): void
    {
        [$fromImage, $tag] = explode(':', $this->image) + [1 => 'latest'];
        /** @var CreateImageStream $imageCreateResponse */
        $imageCreateResponse = $this->dockerClient->imageCreate(null, [
            'fromImage' => $fromImage,
            'tag' => $tag,
        ]);
        $imageCreateResponse->wait();
    }
}
