<?php

declare(strict_types=1);

namespace Testcontainers\Modules;

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForExec;

class PostgresContainer extends GenericContainer
{
    public function __construct(
        string $version = 'latest',
        public readonly string $username = 'test',
        public readonly string $password = 'test',
        public readonly string $database = 'test'
    ) {
        parent::__construct('postgres:' . $version);
        $this->withExposedPorts(5432);
        $this->withEnvironment('POSTGRES_USER', $this->username);
        $this->withEnvironment('POSTGRES_PASSWORD', $this->password);
        $this->withEnvironment('POSTGRES_DB', $this->database);
        $this->withWait(new WaitForExec(["pg_isready", "-h", "127.0.0.1", "-U", $this->username]));
    }

    public function withPostgresUser(string $username): self
    {
        $this->withEnvironment('POSTGRES_USER', $username);

        return $this;
    }

    public function withPostgresPassword(string $password): self
    {
        $this->withEnvironment('POSTGRES_PASSWORD', $password);

        return $this;
    }

    public function withPostgresDatabase(string $database): self
    {
        $this->withEnvironment('POSTGRES_DB', $database);

        return $this;
    }
}
