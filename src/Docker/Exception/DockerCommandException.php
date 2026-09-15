<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Exception;

use RuntimeException;

class DockerCommandException extends RuntimeException
{
    /**
     * @param list<string> $command
     */
    public function __construct(
        string $message,
        private array $command,
        private int $exitCode,
        private string $stderr
    ) {
        $fullMessage = sprintf('%s (exit code %d): %s', $message, $exitCode, implode(' ', $command));
        if (trim($stderr) !== '') {
            $fullMessage .= "\n" . trim($stderr);
        }
        parent::__construct($fullMessage, $exitCode);
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }
}
