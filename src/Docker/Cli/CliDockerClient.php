<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Cli;

use RuntimeException;
use Testcontainers\Docker\DockerClientInterface;
use Testcontainers\Docker\DockerResponse;
use Testcontainers\Docker\Exception\ContainerCreateNotFoundException;
use Testcontainers\Docker\Exception\DockerCommandException;
use Testcontainers\Docker\Model\ContainerCreateResponse;
use Testcontainers\Docker\Model\ContainersCreatePostBody;
use Testcontainers\Docker\Model\ContainersIdExecPostBody;
use Testcontainers\Docker\Model\ContainersIdJsonGetResponse200;
use Testcontainers\Docker\Model\ExecIdJsonGetResponse200;
use Testcontainers\Docker\Model\IdResponse;
use Testcontainers\Docker\Model\Network;
use Testcontainers\Docker\Stream\CreateImageStream;

/**
 * Talks to the container runtime through a Docker-compatible command line
 * binary (`docker`, `podman`, `nerdctl`, ...). Useful where the daemon socket
 * is not reachable from PHP, e.g. rootless Podman without an API service or
 * remote Docker contexts.
 *
 * The API has no notion of separate exec create/start/inspect calls, so exec
 * commands are recorded on containerExec() and executed on execStart().
 */
final class CliDockerClient implements DockerClientInterface
{
    public const DEFAULT_BINARY = 'docker';

    private CommandRunnerInterface $runner;

    /** @var array<string, array{container: string, cmd: list<string>, exitCode: int|null}> */
    private array $execs = [];

    /**
     * @param non-empty-string $binary
     */
    public function __construct(
        private string $binary = self::DEFAULT_BINARY,
        ?CommandRunnerInterface $runner = null
    ) {
        $this->runner = $runner ?? new ProcessCommandRunner();
    }

    /**
     * Creates a client using the binary named in TESTCONTAINERS_CLI_BINARY (default: docker).
     */
    public static function create(?string $binary = null): self
    {
        $binary ??= getenv('TESTCONTAINERS_CLI_BINARY') ?: self::DEFAULT_BINARY;
        if ($binary === '') {
            $binary = self::DEFAULT_BINARY;
        }

        return new self($binary);
    }

    public function getBinary(): string
    {
        return $this->binary;
    }

    public function containerCreate(ContainersCreatePostBody $body, array $query = []): ContainerCreateResponse
    {
        $args = ['create'];

        $name = $query['name'] ?? null;
        if (is_string($name) && $name !== '') {
            $args[] = '--name';
            $args[] = $name;
        }

        $args = [...$args, ...$this->createArguments($body->toArray())];

        $result = $this->runner->run($this->command($args));
        if (!$result->isSuccessful()) {
            if ($this->looksLikeMissingImage($result->stderr)) {
                throw new ContainerCreateNotFoundException('Docker image not found');
            }
            throw $this->commandFailed('Create container', $args, $result);
        }

        return new ContainerCreateResponse(['Id' => trim($result->stdout)]);
    }

    public function containerStart(string $id): void
    {
        $this->runOrFail('Start container', ['start', $id]);
    }

    public function containerStop(string $id): void
    {
        $this->runOrFail('Stop container', ['stop', $id]);
    }

    public function containerDelete(string $id): void
    {
        $args = ['rm', $id];
        $result = $this->runner->run($this->command($args));
        if (!$result->isSuccessful() && !$this->looksLikeMissingContainer($result->stderr)) {
            throw $this->commandFailed('Delete container', $args, $result);
        }
    }

    public function containerRestart(string $id): void
    {
        $this->runOrFail('Restart container', ['restart', $id]);
    }

    public function containerExec(string $id, ContainersIdExecPostBody $body): IdResponse
    {
        $payload = $body->toArray();
        $cmd = [];
        if (isset($payload['Cmd']) && is_array($payload['Cmd'])) {
            $cmd = array_values(array_filter($payload['Cmd'], 'is_string'));
        }

        $execId = bin2hex(random_bytes(32));
        $this->execs[$execId] = [
            'container' => $id,
            'cmd' => $cmd,
            'exitCode' => null,
        ];

        return new IdResponse(['Id' => $execId]);
    }

    public function execStart(string $id, ?array $config = null, int $fetch = self::FETCH_RESPONSE): ?DockerResponse
    {
        if (!isset($this->execs[$id])) {
            throw new RuntimeException("Unknown exec id '{$id}'; call containerExec() first");
        }

        $exec = $this->execs[$id];
        $args = ['exec', $exec['container'], ...$exec['cmd']];

        $result = $this->runner->run($this->command($args));
        $this->execs[$id]['exitCode'] = $result->exitCode;

        if ($this->looksLikeMissingContainer($result->stderr)) {
            throw $this->commandFailed('Start exec', $args, $result);
        }

        // The API returns the multiplexed stdout/stderr stream regardless of the command's exit code.
        return $fetch === self::FETCH_RESPONSE
            ? new DockerResponse(200, $result->stdout . $result->stderr)
            : null;
    }

    public function execInspect(string $id): ExecIdJsonGetResponse200
    {
        if (!isset($this->execs[$id])) {
            throw new RuntimeException("Unknown exec id '{$id}'");
        }

        return new ExecIdJsonGetResponse200(['ExitCode' => $this->execs[$id]['exitCode']]);
    }

    public function containerLogs(string $id, array $params = [], int $fetch = self::FETCH_RESPONSE): ?DockerResponse
    {
        $args = ['logs', $id];
        $result = $this->runner->run($this->command($args));

        if (!$result->isSuccessful()) {
            if ($this->looksLikeMissingContainer($result->stderr)) {
                return $fetch === self::FETCH_RESPONSE ? new DockerResponse(404, trim($result->stderr)) : null;
            }
            throw $this->commandFailed('Container logs', $args, $result);
        }

        return $fetch === self::FETCH_RESPONSE
            ? new DockerResponse(200, $result->stdout . $result->stderr)
            : null;
    }

    public function containerInspect(string $id): ContainersIdJsonGetResponse200
    {
        $result = $this->runOrFail('Inspect container', ['inspect', '--type', 'container', '--format', '{{json .}}', $id]);

        return new ContainersIdJsonGetResponse200($this->decodeJson($result->stdout));
    }

    public function putContainerArchive(string $id, $handle, array $query = [], int $fetch = self::FETCH_RESPONSE): ?DockerResponse
    {
        $path = $query['path'] ?? '/';
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $args = ['cp', '-', $id . ':' . $path];
        $result = $this->runner->run($this->command($args), $handle);
        if (!$result->isSuccessful()) {
            throw $this->commandFailed('Upload container archive', $args, $result);
        }

        return $fetch === self::FETCH_RESPONSE ? new DockerResponse(200, $result->stdout) : null;
    }

    public function imageCreate(?string $name, array $query = [], array $headers = []): CreateImageStream
    {
        $image = $query['fromImage'] ?? $name;
        if (!is_string($image) || $image === '') {
            throw new RuntimeException('imageCreate() requires an image name');
        }

        $tag = $query['tag'] ?? null;
        if (is_string($tag) && $tag !== '' && !str_contains($image, ':') && !str_contains($image, '@')) {
            $image .= ':' . $tag;
        }

        // Registry credentials are handled by the CLI's own login state; X-Registry-Auth headers are ignored.
        $result = $this->runOrFail('Create image', ['pull', $image]);

        return new CreateImageStream($result->stdout . $result->stderr);
    }

    public function networkInspect(string $name): Network
    {
        $result = $this->runOrFail('Inspect network', ['network', 'inspect', '--format', '{{json .}}', $name]);

        return new Network($this->decodeJson($result->stdout));
    }

    /**
     * Translates a /containers/create payload into `docker create` arguments.
     *
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function createArguments(array $payload): array
    {
        $args = [];

        foreach ($this->stringMap($payload['Labels'] ?? null) as $key => $value) {
            $args[] = '--label';
            $args[] = $key . '=' . $value;
        }

        foreach (['Hostname' => '--hostname', 'WorkingDir' => '--workdir', 'User' => '--user'] as $field => $flag) {
            $value = $payload[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $args[] = $flag;
                $args[] = $value;
            }
        }

        foreach ($this->stringList($payload['Env'] ?? null) as $env) {
            $args[] = '--env';
            $args[] = $env;
        }

        $hostConfig = $payload['HostConfig'] ?? [];
        if (!is_array($hostConfig)) {
            $hostConfig = [];
        }

        $args = [...$args, ...$this->portArguments($payload['ExposedPorts'] ?? null, $hostConfig['PortBindings'] ?? null)];

        if (($hostConfig['Privileged'] ?? false) === true) {
            $args[] = '--privileged';
        }
        if (($hostConfig['AutoRemove'] ?? false) === true) {
            $args[] = '--rm';
        }

        $mounts = $hostConfig['Mounts'] ?? null;
        if (is_array($mounts)) {
            foreach ($mounts as $mount) {
                if (!is_array($mount)) {
                    continue;
                }
                $spec = [];
                foreach (['Type' => 'type', 'Source' => 'source', 'Target' => 'target'] as $field => $option) {
                    if (isset($mount[$field]) && is_string($mount[$field])) {
                        $spec[] = $option . '=' . $mount[$field];
                    }
                }
                if ($spec !== []) {
                    $args[] = '--mount';
                    $args[] = implode(',', $spec);
                }
            }
        }

        foreach ($this->stringMap($hostConfig['Tmpfs'] ?? null) as $path => $options) {
            $args[] = '--tmpfs';
            $args[] = $options === '' ? $path : $path . ':' . $options;
        }

        $args = [...$args, ...$this->healthArguments($payload['Healthcheck'] ?? null)];
        $args = [...$args, ...$this->networkArguments($payload['NetworkingConfig'] ?? null)];

        $entrypoint = $this->stringList($payload['Entrypoint'] ?? null);
        $cmd = $this->stringList($payload['Cmd'] ?? null);
        if ($entrypoint !== []) {
            // The CLI flag takes a single executable; remaining entrypoint parts become leading command args.
            $args[] = '--entrypoint';
            $args[] = array_shift($entrypoint);
            $cmd = [...$entrypoint, ...$cmd];
        }

        $image = $payload['Image'] ?? null;
        if (!is_string($image) || $image === '') {
            throw new RuntimeException('Container create request has no image');
        }
        $args[] = $image;

        return [...$args, ...$cmd];
    }

    /**
     * @return list<string>
     */
    private function portArguments(mixed $exposedPorts, mixed $portBindings): array
    {
        $args = [];
        $bound = [];

        if (is_array($portBindings)) {
            foreach ($portBindings as $containerPort => $bindings) {
                if (!is_string($containerPort) || !is_array($bindings)) {
                    continue;
                }
                foreach ($bindings as $binding) {
                    if (!is_array($binding)) {
                        continue;
                    }
                    $hostPort = $binding['HostPort'] ?? '';
                    $hostIp = $binding['HostIp'] ?? '';
                    $spec = is_string($hostPort) ? $hostPort : '';
                    if (is_string($hostIp) && $hostIp !== '') {
                        $spec = $hostIp . ':' . $spec;
                    }
                    $args[] = '--publish';
                    $args[] = $spec === '' ? $containerPort : $spec . ':' . $containerPort;
                    $bound[$containerPort] = true;
                }
            }
        }

        if (is_array($exposedPorts)) {
            foreach (array_keys($exposedPorts) as $containerPort) {
                if (is_string($containerPort) && !isset($bound[$containerPort])) {
                    $args[] = '--expose';
                    $args[] = $containerPort;
                }
            }
        }

        return $args;
    }

    /**
     * @return list<string>
     */
    private function healthArguments(mixed $healthcheck): array
    {
        if (!is_array($healthcheck)) {
            return [];
        }

        $args = [];
        $test = $this->stringList($healthcheck['Test'] ?? null);
        if ($test !== []) {
            $kind = array_shift($test);
            if ($kind === 'NONE') {
                return ['--no-healthcheck'];
            }
            if ($kind === 'CMD-SHELL') {
                $args[] = '--health-cmd';
                $args[] = implode(' ', $test);
            } elseif ($kind === 'CMD') {
                $args[] = '--health-cmd';
                $args[] = implode(' ', array_map('escapeshellarg', $test));
            }
        }

        foreach (['Interval' => '--health-interval', 'Timeout' => '--health-timeout', 'StartPeriod' => '--health-start-period'] as $field => $flag) {
            $nanoseconds = $healthcheck[$field] ?? null;
            if (is_int($nanoseconds) && $nanoseconds > 0) {
                $args[] = $flag;
                $args[] = $nanoseconds . 'ns';
            }
        }

        $retries = $healthcheck['Retries'] ?? null;
        if (is_int($retries) && $retries > 0) {
            $args[] = '--health-retries';
            $args[] = (string) $retries;
        }

        return $args;
    }

    /**
     * @return list<string>
     */
    private function networkArguments(mixed $networkingConfig): array
    {
        if (!is_array($networkingConfig)) {
            return [];
        }
        $endpoints = $networkingConfig['EndpointsConfig'] ?? null;
        if (!is_array($endpoints)) {
            return [];
        }

        $args = [];
        foreach ($endpoints as $network => $settings) {
            if (!is_string($network)) {
                continue;
            }
            $args[] = '--network';
            $args[] = $network;

            if (is_array($settings)) {
                foreach ($this->stringList($settings['Aliases'] ?? null) as $alias) {
                    $args[] = '--network-alias';
                    $args[] = $alias;
                }
            }
            // The CLI only accepts one network at create time; more must be connected after creation.
            break;
        }

        return $args;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $args
     * @return non-empty-list<string>
     */
    private function command(array $args): array
    {
        return [$this->binary, ...$args];
    }

    /**
     * @param list<string> $args
     */
    private function runOrFail(string $action, array $args): CommandResult
    {
        $result = $this->runner->run($this->command($args));
        if (!$result->isSuccessful()) {
            throw $this->commandFailed($action, $args, $result);
        }

        return $result;
    }

    /**
     * @param list<string> $args
     */
    private function commandFailed(string $action, array $args, CommandResult $result): DockerCommandException
    {
        return new DockerCommandException($action . ' failed', $this->command($args), $result->exitCode, $result->stderr);
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(string $json): array
    {
        $data = json_decode(trim($json), true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON output from ' . $this->binary);
        }

        // `inspect` without --format yields a one-element array; unwrap it for robustness.
        if (array_is_list($data) && count($data) === 1 && is_array($data[0])) {
            return $data[0];
        }

        return $data;
    }

    private function looksLikeMissingImage(string $stderr): bool
    {
        return (bool) preg_match('/manifest unknown|not found|pull access denied|unable to find image|no such image|does not exist/i', $stderr);
    }

    private function looksLikeMissingContainer(string $stderr): bool
    {
        return (bool) preg_match('/no such container|no container with name or ID/i', $stderr);
    }
}
