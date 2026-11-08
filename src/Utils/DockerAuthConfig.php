<?php

declare(strict_types=1);

namespace Testcontainers\Utils;

use RuntimeException;
use JsonException;

class DockerAuthConfig
{
    private const DEFAULT_CONFIG_PATHS = [
        '~/.docker/config.json',
        '/etc/docker/config.json',
    ];

    private static ?self $instance = null;

    /**
     * @var array<string, array{auth?: string, username?: string, password?: string, email?: string}>
     */
    private array $auths = [];

    private ?string $credsStore = null;

    /**
     * @var array<string, string>
     */
    private array $credHelpers = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Get the singleton instance of DockerAuthConfig.
     * This avoids re-reading config files/environment on every image pull.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Get authentication for a specific registry
     *
     * @return array{username: string, password: string}|null
     */
    public function getAuthForRegistry(string $registry): ?array
    {
        $registry = $this->normalizeRegistry($registry);

        if (isset($this->auths[$registry])) {
            $auth = $this->auths[$registry];

            if (isset($auth['auth'])) {
                $decoded = base64_decode($auth['auth'], true);
                if ($decoded === false) {
                    throw new RuntimeException('Invalid base64 auth string');
                }

                if (!str_contains($decoded, ':')) {
                    throw new RuntimeException('Invalid auth format');
                }
                [$username, $password] = explode(':', $decoded, 2);
                return ['username' => $username, 'password' => $password];
            }

            if (isset($auth['username']) && isset($auth['password'])) {
                return ['username' => $auth['username'], 'password' => $auth['password']];
            }
        }

        if (isset($this->credHelpers[$registry])) {
            return $this->getCredentialsFromHelper($this->credHelpers[$registry], $registry);
        }

        if ($this->credsStore !== null) {
            return $this->getCredentialsFromHelper($this->credsStore, $registry);
        }

        return null;
    }

    /**
     * Load Docker configuration from environment or default paths
     */
    private function loadConfig(): void
    {
        $configData = null;

        $envConfig = getenv('DOCKER_AUTH_CONFIG');
        if ($envConfig !== false && $envConfig !== '') {
            try {
                $configData = json_decode($envConfig, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new RuntimeException('Invalid JSON in DOCKER_AUTH_CONFIG: ' . $e->getMessage(), 0, $e);
            }
        } else {
            foreach (self::DEFAULT_CONFIG_PATHS as $path) {
                $expandedPath = str_replace('~', getenv('HOME') ?: '', $path);
                if (file_exists($expandedPath)) {
                    $content = file_get_contents($expandedPath);
                    if ($content === false) {
                        continue;
                    }

                    try {
                        $configData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        throw new RuntimeException("Invalid JSON in $expandedPath: " . $e->getMessage(), 0, $e);
                    }
                    break;
                }
            }
        }

        if (!is_array($configData)) {
            return;
        }

        if (isset($configData['auths']) && is_array($configData['auths'])) {
            /** @var array<string, array{auth?: string, username?: string, password?: string, email?: string}> $auths */
            $auths = $configData['auths'];
            $this->auths = $auths;
        }

        if (isset($configData['credsStore']) && is_string($configData['credsStore'])) {
            $this->credsStore = $configData['credsStore'];
        }

        if (isset($configData['credHelpers']) && is_array($configData['credHelpers'])) {
            /** @var array<string, string> $credHelpers */
            $credHelpers = $configData['credHelpers'];
            $this->credHelpers = $credHelpers;
        }
    }

    /**
     * Get credentials from a credential helper
     *
     * @return array{username: string, password: string}|null
     */
    private function getCredentialsFromHelper(string $helper, string $registry): ?array
    {
        $helperCommand = 'docker-credential-' . $helper;

        $checkCommand = sprintf('command -v %s 2>/dev/null', escapeshellarg($helperCommand));
        $helperPath = trim(shell_exec($checkCommand) ?: '');

        if (empty($helperPath)) {
            return null;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([$helperCommand, 'get'], $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException("Failed to execute credential helper: $helperCommand");
        }

        fwrite($pipes[0], $registry);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            if ($stderr !== false && strpos($stderr, 'credentials not found') !== false) {
                return null;
            }
            throw new RuntimeException("Credential helper failed: $stderr");
        }

        if ($stdout === false) {
            return null;
        }

        try {
            $credentials = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON from credential helper: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($credentials)) {
            throw new RuntimeException('Credential helper returned invalid response');
        }

        if (!isset($credentials['Username']) || !isset($credentials['Secret']) ||
            !is_string($credentials['Username']) || !is_string($credentials['Secret'])) {
            return null;
        }

        return [
            'username' => $credentials['Username'],
            'password' => $credentials['Secret'],
        ];
    }

    /**
     * Normalize registry URL to match Docker config format
     */
    private function normalizeRegistry(string $registry): string
    {
        $normalized = preg_replace('#^https?://#', '', $registry);

        if ($normalized === null) {
            $normalized = $registry;
        }

        $normalized = rtrim($normalized, '/');

        if ($normalized === 'docker.io' || $normalized === 'index.docker.io' || $normalized === 'registry-1.docker.io') {
            return 'https://index.docker.io/v1/';
        }

        return $normalized;
    }

    public static function getRegistryFromImage(string $image): string
    {
        $registry = DockerImageName::parse($image)->getRegistry();

        return $registry !== '' ? $registry : 'docker.io';
    }
}
