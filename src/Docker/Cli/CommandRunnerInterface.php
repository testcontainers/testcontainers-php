<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Cli;

interface CommandRunnerInterface
{
    /**
     * Runs a command and waits for it to finish.
     *
     * @param non-empty-list<string> $command Executable followed by its arguments
     * @param resource|string|null $stdin Data to feed to the process' standard input
     */
    public function run(array $command, $stdin = null): CommandResult;
}
