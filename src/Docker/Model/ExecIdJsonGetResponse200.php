<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class ExecIdJsonGetResponse200
{
    private ?int $exitCode = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        if (array_key_exists('ExitCode', $data) && (is_int($data['ExitCode']) || is_null($data['ExitCode']))) {
            $this->exitCode = $data['ExitCode'];
        }
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }
}
