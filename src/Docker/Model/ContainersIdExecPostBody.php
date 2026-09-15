<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class ContainersIdExecPostBody
{
    /** @var array<int, string> */
    private array $cmd = [];
    private bool $attachStdout = false;
    private bool $attachStderr = false;

    /**
     * @param array<int, string> $cmd
     */
    public function setCmd(array $cmd): self
    {
        $this->cmd = $cmd;

        return $this;
    }

    public function setAttachStdout(bool $attachStdout): self
    {
        $this->attachStdout = $attachStdout;

        return $this;
    }

    public function setAttachStderr(bool $attachStderr): self
    {
        $this->attachStderr = $attachStderr;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'Cmd' => $this->cmd,
            'AttachStdout' => $this->attachStdout,
            'AttachStderr' => $this->attachStderr,
        ];
    }
}
