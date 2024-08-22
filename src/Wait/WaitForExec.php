<?php

declare(strict_types=1);

namespace Testcontainers\Wait;

use Closure;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\ExecIdStartPostBody;
use Docker\Docker;
use Testcontainers\Exception\ContainerNotReadyException;

class WaitForExec implements WaitInterface
{
    protected Docker $dockerClient;

    protected ContainersIdExecPostBody $execConfig;

    /**
     * @param array<string> $command
     */
    public function __construct(private array $command, private ?Closure $checkFunction = null)
    {
        $this->dockerClient = Docker::create();
        $execConfig = new ContainersIdExecPostBody();
        $execConfig->setTty(true);
        $execConfig->setAttachStdout(true);
        $execConfig->setAttachStderr(true);
        $execConfig->setCmd($this->command);
    }

    public function wait(string $id): void
    {
        $execid = $this->dockerClient->containerExec($id, $this->execConfig)->getId() ?? '';
        $execStartConfig = new ExecIdStartPostBody();
        $execStartConfig->setDetach(false);
        $this->dockerClient->execStart($execid, $execStartConfig);
    }
}
