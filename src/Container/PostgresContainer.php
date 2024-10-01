<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Utils\PortGenerator\FixedPortGenerator;
use Testcontainers\Wait\WaitForExec;

/**
 * Left for namespace backward compatibility
 * @deprecated Use \Testcontainers\Modules\PostgresContainer instead.
 * TODO: Remove in next major release.
 */
class PostgresContainer extends Container
{
    public function __construct(
        string $version = 'latest',
        public readonly string $username = 'test',
        public readonly string $password = 'test',
        public readonly string $database = 'test'
    ) {
        parent::__construct('postgres:' . $version);
        $this->withPortGenerator(new FixedPortGenerator([5432]));
        $this->withExposedPorts(5432);
        $this->withEnvironment('POSTGRES_USER', $this->username);
        $this->withEnvironment('POSTGRES_PASSWORD', $this->password);
        $this->withEnvironment('POSTGRES_DB', $this->database);
        $this->withWait(new WaitForExec(["pg_isready", "-h", "127.0.0.1", "-U", $this->username]));
    }

    public static function make(string $version = 'latest', string $dbPassword = 'root'): self
    {
        return new self(
            version: $version,
            password: $dbPassword
        );
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
