<?php

declare(strict_types=1);

namespace Testcontainer\Trait;

use JsonException;
use Symfony\Component\Process\Process;
use UnexpectedValueException;

/**
 * @phpstan-import-type ContainerInspect from \Testcontainer\Container\Container
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
    protected function getContainerAddress(string $containerId, ?string $networkName = null, ?array $inspectedData = null): string
    {
        if (! is_array($inspectedData)) {
            $process = new Process(['docker', 'inspect', $containerId]);
            $process->mustRun();

            /** @var ContainerInspect $inspectedData */
            $inspectedData = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
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
}
