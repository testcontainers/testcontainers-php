<?php

declare(strict_types=1);

namespace Testcontainers\Modules;

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForExec;

class MariaDBContainer extends GenericContainer
{
    public function __construct(string $version = 'latest', string $mysqlRootPassword = 'root')
    {
        parent::__construct('mariadb:' . $version);
        $this->withExposedPorts(3306);
        $this->withEnvironment('MARIADB_ROOT_PASSWORD', $mysqlRootPassword);
        $this->withWait(new WaitForExec([
            "mariadb-admin",
            "ping",
            "-h", "127.0.0.1",
        ]));
    }

    public function withMariaDBUser(string $username, string $password): self
    {
        $this->withEnvironment('MARIADB_USER', $username);
        $this->withEnvironment('MARIADB_PASSWORD', $password);

        return $this;
    }

    public function withMariaDBDatabase(string $database): self
    {
        $this->withEnvironment('MARIADB_DATABASE', $database);

        return $this;
    }
}
