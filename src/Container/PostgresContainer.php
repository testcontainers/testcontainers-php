<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Wait\WaitForExec;

class PostgresContainer extends GenericContainer
{
    public function __construct(string $version = 'latest', string $rootPassword = 'root')
    {
        parent::__construct('postgres:' . $version);
        $this->withEnvironment('POSTGRES_PASSWORD', $rootPassword);
        $this->withWait(new WaitForExec(["pg_isready", "-h", "127.0.0.1"]));
    }

    /**
     *  @deprecated Use constructor instead
     *  Left for backward compatibility
     */
    public static function make(string $version = 'latest', string $dbPassword = 'root'): self
    {
        return new self($version, $dbPassword);
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
