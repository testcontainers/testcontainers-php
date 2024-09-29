<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Utils\PortGenerator\FixedPortGenerator;
use Testcontainers\Wait\WaitForExec;

/**
 * Left for namespace backward compatibility
 * @deprecated Use \Testcontainers\Modules\MariaDBContainer instead.
 * TODO: Remove in next major release.
 */
class MariaDBContainer extends Container
{
    public function __construct(string $version = 'latest', string $mysqlRootPassword = 'root')
    {
        parent::__construct('mariadb:' . $version);
        $this->withPortGenerator(new FixedPortGenerator([3306]));
        $this->withExposedPorts(3306);
        $this->withEnvironment('MARIADB_ROOT_PASSWORD', $mysqlRootPassword);
        $this->withWait(new WaitForExec([
            "mariadb-admin",
            "ping",
            "-h", "127.0.0.1",
        ]));
    }

    public static function make(string $version = 'latest', string $mysqlRootPassword = 'root'): self
    {
        return new self($version, $mysqlRootPassword);
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
