<?php

declare(strict_types=1);

namespace Testcontainers\Modules;

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForExec;

class MySQLContainer extends GenericContainer
{
    public function __construct(string $version = 'latest', string $mysqlRootPassword = 'root')
    {
        parent::__construct('mysql:' . $version);
        $this->withExposedPorts(3306);
        $this->withEnvironment('MYSQL_ROOT_PASSWORD', $mysqlRootPassword);
        $this->withWait(new WaitForExec([
            "mysqladmin",
            "ping",
            "-h", "127.0.0.1",
        ]));
    }

    public function withMySQLUser(string $username, string $password): self
    {
        $this->withEnvironment('MYSQL_USER', $username);
        $this->withEnvironment('MYSQL_PASSWORD', $password);

        return $this;
    }

    public function withMySQLDatabase(string $database): self
    {
        $this->withEnvironment('MYSQL_DATABASE', $database);

        return $this;
    }
}
