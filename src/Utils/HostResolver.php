<?php

declare(strict_types=1);

namespace Testcontainers\Utils;

use Testcontainers\Docker\DockerClient;
use Testcontainers\Docker\Model\Network;
use RuntimeException;
use Testcontainers\Container\GenericContainer;
use Testcontainers\ContainerClient\DockerContainerClient;

class HostResolver
{
    public function __construct(protected ?DockerClient $dockerClient = null)
    {
        $this->dockerClient = $dockerClient ?? DockerContainerClient::getDockerClient();
    }

    /**
     * Resolves the host address for connecting to a container.
     *
     * The resolution process is as follows:
     * 1. If user overrides are allowed and TESTCONTAINERS_HOST_OVERRIDE is set, its value is returned.
     * 2. Otherwise, the DOCKER_HOST environment variable is parsed.
     *    - If the scheme is one of http, https, or tcp, the hostname is used.
     *    - If the scheme is unix or npipe and the process is running in a container, the network gateway
     *      is determined by inspecting the relevant Docker network or running a temporary container.
     * 3. If no other value can be determined, "localhost" is returned.
     *
     * @return string
     * @throws RuntimeException If the DOCKER_HOST scheme is unsupported.
     */
    public function resolveHost(): string
    {
        if ($this->allowUserOverrides() && ($override = getenv('TESTCONTAINERS_HOST_OVERRIDE')) !== false) {
            return $override;
        }

        // Get DOCKER_HOST URI, defaulting to a TCP endpoint if not set.
        $dockerHostUri = getenv('DOCKER_HOST') ?: 'tcp://127.0.0.1:2375';
        $parts = parse_url($dockerHostUri);
        if ($parts === false || !isset($parts['scheme'])) {
            return 'localhost';
        }

        $scheme = $parts['scheme'];

        switch ($scheme) {
            case 'http':
            case 'https':
            case 'tcp':
                return $parts['host'] ?? 'localhost';

            case 'unix':
            case 'npipe':
                if ($this->isInContainer()) {
                    // If using podman, choose "podman" network; otherwise, use "bridge"
                    $networkName = (str_contains($dockerHostUri, 'podman.sock')) ? 'podman' : 'bridge';
                    if ($gateway = $this->findGateway($networkName)) {
                        return $gateway;
                    }
                    if ($defaultGateway = $this->findDefaultGateway()) {
                        return $defaultGateway;
                    }
                }
                return 'localhost';

            default:
                throw new RuntimeException("Unsupported Docker host scheme: {$scheme}");
        }
    }

    protected function allowUserOverrides(): bool
    {
        return true;
    }

    /**
     * Determines if the code is running inside a container.
     */
    protected function isInContainer(): bool
    {
        return file_exists('/.dockerenv');
    }

    /**
     * Inspects the given network and returns its gateway IP address if found.
     *
     * @param string $networkName
     * @return string|null
     */
    protected function findGateway(string $networkName): ?string
    {
        try {
            /** @var Network|null $networkInspect */
            $networkInspect = $this->dockerClient?->networkInspect($networkName);
            $ipamConfig = $networkInspect?->getIPAM()?->getConfig();
            if ($ipamConfig !== null) {
                foreach ($ipamConfig as $config) {
                    if ($config->getGateway() !== null) {
                        return $config->getGateway();
                    }
                }
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    /**
     * Runs a temporary container to determine the default gateway.
     */
    protected function findDefaultGateway(): ?string
    {
        $tmpContainer = null;
        try {
            // Create a temporary container using a lightweight Alpine image.
            $tmpContainer = (new GenericContainer('alpine:3.14'))
                ->withCommand(['tail', '-f', '/dev/null'])
                ->start();
            $result =  $tmpContainer->exec(['sh', '-c', "ip route | awk '/default/ { print $3 }'"]);
            $tmpContainer->stop();
            return $result;
        } catch (\Throwable) {
            return null;
        } finally {
            if ($tmpContainer !== null) {
                try {
                    $tmpContainer->stop();
                } catch (\Throwable) {
                    //
                }
            }
        }
    }
}
