<?php

declare(strict_types=1);

namespace Testcontainers\Trait;

use JsonException;
use Symfony\Component\Process\Process;
use Testcontainers\Container\Container;
use UnexpectedValueException;

/**
 * @phpstan-import-type ContainerInspect from Container
 * @phpstan-import-type DockerNetwork from Container
 */
trait DockerContainerAwareTrait
{
    /**
     * @param string $containerId
     * @param string|null $networkName
     * @param ContainerInspect|null $inspectedData
     * @return string
     *
     * @throws JsonException
     */
    private static function dockerContainerAddress(string $containerId, ?string $networkName = null, ?array $inspectedData = null): string
    {
        if (! is_array($inspectedData)) {
            $inspectedData = self::dockerContainerInspect($containerId);
        }

        if (is_string($networkName)) {
            $containerAddress = $inspectedData[0]['NetworkSettings']['Networks'][$networkName]['IPAddress'] ?? null;

            if (is_string($containerAddress)) {
                return $containerAddress;
            }
        }

        $containerAddress = $inspectedData[0]['NetworkSettings']['IPAddress'] ?? null;

        if (is_string($containerAddress)) {
            return $containerAddress;
        }

        throw new UnexpectedValueException('Unable to find container IP address');
    }

    /**
     * @param string $containerId
     * @return ContainerInspect
     *
     * @throws JsonException
     */
    private static function dockerContainerInspect(string $containerId): array
    {
        $process = new Process(['docker', 'inspect', $containerId]);
        $process->mustRun();

        /** @var ContainerInspect */
        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $networkName
     * @return DockerNetwork|false
     *
     * @throws JsonException
     */
    private static function dockerNetworkFind(string $networkName): array|false
    {
        $process = new Process(['docker', 'network', 'ls', '--format', 'json', '--filter', 'name=' . $networkName]);
        $process->mustRun();

        $json = $process->getOutput();

        if ($json === '') {
            return false;
        }

        $json = str_replace("\n", ',', $json);
        $json = '['. rtrim($json, ',') .']';

        /** @var array<int, DockerNetwork> $output */
        $output = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        /** @var array<int, DockerNetwork> $matchingNetworks */
        $matchingNetworks = array_filter($output, static fn (array $network) => $network['Name'] === $networkName);

        if (count($matchingNetworks) === 0) {
            return false;
        }

        return $matchingNetworks[0];
    }

    private static function dockerNetworkCreate(string $networkName, string $driver = 'bridge'): void
    {
        $process = new Process(['docker', 'network', 'create', '--driver', $driver, $networkName]);
        $process->mustRun();
    }

    private static function dockerNetworkRemove(string $networkName): void
    {
        $process = new Process(['docker', 'network', 'rm', $networkName, '-f']);
        $process->mustRun();
    }
}
