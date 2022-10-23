<?php

declare(strict_types=1);

namespace Testcontainer\Container;

use Testcontainer\Wait\WaitForExec;

class PostgresContainer extends Container
{
    private function __construct(string $version, string $rootPassword)
    {
        parent::__construct('postgres:' . $version);
        $this->withEnvironment('POSTGRES_PASSWORD', $rootPassword);
        $this->withWait(new WaitForExec(["pg_isready", "-h", "127.0.0.1"]));
    }

    public static function make(string $version = 'latest', string $mysqlRootPassword = 'root'): self
    {
        return new self($version, $mysqlRootPassword);
    }

    public function withPostgresUser(string $username): self
    {
        $this->withEnvironment('POSTGRES_USER', $username);

        return $this;
    }

    public function withPostgresDatabase(string $database): self
    {
        $this->withEnvironment('POSTGRES_DB', $database);

        return $this;
    }
}
