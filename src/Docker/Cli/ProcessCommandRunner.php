<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Cli;

use RuntimeException;

/**
 * Runs commands with proc_open, inheriting the current environment so that
 * settings like DOCKER_HOST, DOCKER_CONTEXT or CONTAINER_HOST apply.
 */
final class ProcessCommandRunner implements CommandRunnerInterface
{
    public function run(array $command, $stdin = null): CommandResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start process: ' . implode(' ', $command));
        }

        if (is_resource($stdin)) {
            stream_copy_to_stream($stdin, $pipes[0]);
        } elseif (is_string($stdin) && $stdin !== '') {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);

        [$stdout, $stderr] = $this->drain($pipes[1], $pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new CommandResult($exitCode, $stdout, $stderr);
    }

    /**
     * Reads both output pipes concurrently so that neither can fill up and block the process.
     *
     * @param resource $stdoutPipe
     * @param resource $stderrPipe
     * @return array{string, string}
     */
    private function drain($stdoutPipe, $stderrPipe): array
    {
        $stdout = '';
        $stderr = '';

        stream_set_blocking($stdoutPipe, false);
        stream_set_blocking($stderrPipe, false);

        $open = [1 => $stdoutPipe, 2 => $stderrPipe];

        while ($open !== []) {
            $read = array_values($open);
            $write = null;
            $except = null;

            $ready = @stream_select($read, $write, $except, 1);
            if ($ready === false) {
                // stream_select is not available for pipes on every platform; fall back to sequential reads.
                $stdout .= (string) stream_get_contents($stdoutPipe);
                $stderr .= (string) stream_get_contents($stderrPipe);
                break;
            }

            foreach ($open as $index => $pipe) {
                $chunk = fread($pipe, 65536);
                if ($chunk !== false && $chunk !== '') {
                    if ($index === 1) {
                        $stdout .= $chunk;
                    } else {
                        $stderr .= $chunk;
                    }
                }
                if (feof($pipe)) {
                    unset($open[$index]);
                }
            }
        }

        return [$stdout, $stderr];
    }
}
