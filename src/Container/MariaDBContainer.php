<?php

declare(strict_types=1);

namespace Testcontainer\Container;

use Testcontainer\Wait\WaitForExec;

class MariaDBContainer extends Container
{
    private function __construct(string $version, string $mysqlRootPassword)
    {
        parent::__construct('mariadb:' . $version);
        $this->withEnvironment('MARIADB_ROOT_PASSWORD', $mysqlRootPassword);
        $this->withWait(new WaitForExec(['mysqladmin', 'ping', '-h', '127.0.0.1']));
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
