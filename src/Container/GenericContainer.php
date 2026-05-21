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
use RuntimeException;
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Utils\PortGenerator\PortGenerator;
use Testcontainers\Utils\PortGenerator\RandomUniquePortGenerator;
use Testcontainers\Utils\PortNormalizer;
use Testcontainers\Utils\TarBuilder;
use Testcontainers\Wait\WaitForContainer;
use Testcontainers\Wait\WaitStrategy;
use Testcontainers\Utils\DockerAuthConfig;

class GenericContainer implements TestContainer
{
    protected Docker $dockerClient;

    protected string $image;

    protected ?string $name = null;

    /**
     * User-defined key/value metadata.
     * @var array<string, string>|null $labels
     */
    protected ?array $labels = null;

    protected ?string $hostname = null;

    protected string $id;

    /** @var list<string> */
    protected array $command = [];

    protected bool $autoRemove = false;

    protected ?string $entryPoint = null;

    protected ?HealthConfig $healthConfig = null;

    /**
    * @var array<string, string>
    */
    protected array $env = [];

    protected WaitStrategy $waitStrategy;

    protected PortGenerator $portGenerator;

    protected bool $isPrivileged = false;

    protected ?string $networkName = null;

    /** @var array<string> */
    protected array $aliases = [];

    protected ?string $user = null;

    protected ?string $workingDir = null;

    /**
     * @var array<array{source: string, target: string, mode?: int}>
     */
    protected array $filesToCopy = [];

    /**
     * @var array<array{source: string, target: string, mode?: int}>
     */
    protected array $directoriesToCopy = [];

    /**
     * @var array<array{content: string, target: string, mode?: int}>
     */
    protected array $contentsToCopy = [];

    protected int $startAttempts = 0;
    protected const MAX_START_ATTEMPTS = 2;

    /**
     * @var array<Mount>
     */
    protected array $mounts = [];

    /** @var array<string, string> */
    protected array $tmpfs = [];

    /** @var array<string> List of exposed ports in the format ['8080/tcp'] */
    protected array $exposedPorts = [];

    public function __construct(string $image)
    {
        $this->image = $image;
        $this->dockerClient = DockerContainerClient::getDockerClient();
        $this->waitStrategy = new WaitForContainer();
        $this->portGenerator = new RandomUniquePortGenerator();
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

    public function withAutoRemove(bool $autoRemove): static
    {
        $this->autoRemove = $autoRemove;

        return $this;
    }

    /**
     * @param array<array{source: string, target: string, mode?: int}> $files
     */
    public function withCopyFilesToContainer(array $files): static
    {
        $this->filesToCopy = array_merge($this->filesToCopy, $files);

        return $this;
    }

    /**
     * @param array<array{source: string, target: string, mode?: int}> $directories
     */
    public function withCopyDirectoriesToContainer(array $directories): static
    {
        $this->directoriesToCopy = array_merge($this->directoriesToCopy, $directories);

        return $this;
    }

    /**
     * @param array<array{content: string, target: string, mode?: int}> $contents
     */
    public function withCopyContentToContainer(array $contents): static
    {
        $this->contentsToCopy = array_merge($this->contentsToCopy, $contents);

        return $this;
    }

    public function withEntryPoint(string $entryPoint): static
    {
        $this->entryPoint = $entryPoint;

        return $this;
    }

    /**
     * @param array<string, string> $env An array of key-value pairs: $object->withEnvironment(['key1' => 'value1', 'key2' => 'value2']);
     * @return static Returns itself for chaining purposes.
     */
    public function withEnvironment(array $env): static
    {
        foreach ($env as $key => $val) {
            $this->env[$key] = $val;
        }

        return $this;
    }

    public function withWait(WaitStrategy $waitStrategy): static
    {
        $this->waitStrategy = $waitStrategy;

        return $this;
    }

    public function withHealthCheckCommand(
        string $command,
        int $intervalInMilliseconds = 1000,
        int $timeoutInMilliseconds = 3000,
        int $retries = 3,
        int $startPeriodInMilliseconds = 0
    ): static {
        $this->healthConfig = new HealthConfig();
        $this->healthConfig->setTest(['CMD-SHELL', $command]);
        $this->healthConfig->setInterval($intervalInMilliseconds * 1_000_000);
        $this->healthConfig->setTimeout($timeoutInMilliseconds * 1_000_000);
        $this->healthConfig->setRetries($retries);
        $this->healthConfig->setStartPeriod($startPeriodInMilliseconds * 1_000_000);

        return $this;
    }

    public function withHostname(string $hostname): static
    {
        $this->hostname = $hostname;

        return $this;
    }

    public function withMount(string $localPath, string $containerPath): static
    {
        $this->mounts[] = new Mount([
            'type' => 'bind',
            'source' => $localPath,
            'target' => $containerPath,
        ]);

        return $this;
    }

    public function withTmpfs(string $containerPath, string $options = 'rw,noexec'): static
    {
        $this->tmpfs[$containerPath] = $options;

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

    public function withName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function withLabels(array $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    public function withPrivilegedMode(bool $privileged = true): static
    {
        $this->isPrivileged = $privileged;

        return $this;
    }

    public function withNetwork(string $networkName): static
    {
        $this->networkName = $networkName;

        return $this;
    }

    /**
     * @param array<string> $aliases
     */
    public function withAliases(array $aliases): static
    {
        $this->aliases = $aliases;

        return $this;
    }

    public function withPortGenerator(PortGenerator $portGenerator): static
    {
        $this->portGenerator = $portGenerator;

        return $this;
    }

    public function withUser(string $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function withWorkingDir(string $workingDir): static
    {
        $this->workingDir = $workingDir;

        return $this;
    }

    public function start(): StartedGenericContainer
    {
        $this->startAttempts++;
        $containerConfig = $this->createContainerConfig();
        $queryParameters = [];
        if ($this->name !== null) {
            $queryParameters['name'] = $this->name;
        }
        try {
            /** @var ContainerCreateResponse|null $containerCreateResponse */
            $containerCreateResponse = $this->dockerClient->containerCreate($containerConfig, $queryParameters);
            $this->id = $containerCreateResponse?->getId() ?? '';
        } catch (ContainerCreateNotFoundException) {
            if ($this->startAttempts >= self::MAX_START_ATTEMPTS) {
                throw new RuntimeException("Failed to start container after pulling image.");
            }
            // If the image is not found, pull it and try again
            // TODO: add withPullPolicy support
            $this->pullImage();
            return $this->start();
        }

        $this->dockerClient->containerStart($this->id);

        if ($this->filesToCopy !== [] || $this->directoriesToCopy !== [] || $this->contentsToCopy !== []) {
            $this->copyToContainer();
        }

        $startedContainer = new StartedGenericContainer($this->id);
        $this->waitStrategy->wait($startedContainer);

        return $startedContainer;
    }

    /**
     * Uploads a tar archive containing files/directories/content to the container,
     * extracting it into a chosen directory (`$containerPath`). Allows setting
     * Docker's `noOverwriteDirNonDir` and `copyUIDGID` query parameters.
     *
     * @param string $containerPath Path within the container to extract the tar contents. Must be a directory in the container.
     * @param bool $noOverwriteDirNonDir If true, Docker will error if it would replace an existing directory with a non-directory and vice versa.
     * @param bool $copyUIDGID If true, Docker will attempt to preserve UID/GID from the tar entries.
     * @throws RuntimeException|InvalidArgumentException
     */
    protected function copyToContainer(
        string $containerPath = '/',
        bool $noOverwriteDirNonDir = false,
        bool $copyUIDGID = false
    ): void {
        $tarBuilder = new TarBuilder();
        foreach ($this->filesToCopy as $file) {
            $tarBuilder->addFile($file['source'], $file['target'], $file['mode'] ?? null);
        }

        foreach ($this->directoriesToCopy as $directory) {
            $tarBuilder->addDirectory($directory['source'], $directory['target'], $directory['mode'] ?? null);
        }

        foreach ($this->contentsToCopy as $content) {
            $tarBuilder->addContent($content['content'], $content['target'], $content['mode'] ?? null);
        }

        $tarFilePath = $tarBuilder->buildTarArchive();

        if (!is_file($tarFilePath)) {
            throw new RuntimeException("Tar file does not exist at: $tarFilePath");
        }

        $handle = fopen($tarFilePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open temporary tar archive at: $tarFilePath");
        }

        $queryParams = [
            'path' => $containerPath,
        ];

        if ($noOverwriteDirNonDir) {
            $queryParams['noOverwriteDirNonDir'] = 'true';
        }

        if ($copyUIDGID) {
            $queryParams['copyUIDGID'] = 'true';
        }

        /**
         * TODO: should be improved. Currently without using dummy $result or FETCH_RESPONSE, the request is failing.
         * Probably an issue with the beluga-php/docker-php client library.
         * */
        $result = $this->dockerClient->putContainerArchive(
            $this->id,
            $handle,
            $queryParams,
            $this->dockerClient::FETCH_RESPONSE
        );

        fclose($handle);
        unlink($tarFilePath);
    }


    protected function createContainerConfig(): ContainersCreatePostBody
    {
        $containerCreatePostBody = new ContainersCreatePostBody();
        $containerCreatePostBody->setImage($this->image);
        $containerCreatePostBody->setCmd($this->command);
        $containerCreatePostBody->setLabels($this->labels);
        $containerCreatePostBody->setHostname($this->hostname);
        $containerCreatePostBody->setWorkingDir($this->workingDir);
        $containerCreatePostBody->setUser($this->user);

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
            $aliases = $this->aliases ?? [];
            if ($this->name) {
                $aliases[] = $this->name;
            }
            $endpointsConfig = [
                $this->networkName => new EndpointSettings([
                    'networkID' => $this->networkName,
                    'aliases' => $aliases,
                ]),
            ];
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
        if ($this->exposedPorts === [] && !$this->isPrivileged && $this->mounts === [] && $this->tmpfs === []) {
            return null;
        }

        $hostConfig = new HostConfig();

        $hostConfig->setAutoRemove($this->autoRemove);

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

        if ($this->tmpfs !== []) {
            $hostConfig->setTmpfs($this->tmpfs);
        }

        return $hostConfig;
    }

    /**
     * @return array<string, array<int, PortBinding>>
     */
    protected function createPortBindings(): array
    {
        $portBindings = [];

        foreach ($this->exposedPorts as $port) {
            $portBinding = new PortBinding();
            $portBinding->setHostPort((string)$this->portGenerator->generatePort());
            $portBinding->setHostIp('0.0.0.0');
            $portBindings[$port] = [$portBinding];
        }

        return $portBindings;
    }

    protected function pullImage(): void
    {
        [$fromImage, $tag] = explode(':', $this->image) + [1 => 'latest'];

        // Build headers for the request
        $headers = [];

        // Try to get authentication for the registry
        $registry = DockerAuthConfig::getRegistryFromImage($fromImage);
        $credentials = DockerAuthConfig::getInstance()->getAuthForRegistry($registry);

        if ($credentials !== null) {
            // Docker expects the X-Registry-Auth header to be a base64-encoded JSON
            $authData = [
                'username' => $credentials['username'],
                'password' => $credentials['password'],
            ];
            $headers['X-Registry-Auth'] = base64_encode(json_encode($authData, JSON_THROW_ON_ERROR));
        }

        /** @var CreateImageStream $imageCreateResponse */
        $imageCreateResponse = $this->dockerClient->imageCreate(
            null,
            [
                'fromImage' => $fromImage,
                'tag' => $tag,
            ],
            $headers
        );
        $imageCreateResponse->wait();
    }
}
