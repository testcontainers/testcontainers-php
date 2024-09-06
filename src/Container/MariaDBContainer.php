<?php

declare(strict_types=1);

namespace Testcontainers\Container;

use Testcontainers\Wait\WaitForLog;

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
        $this->withExposedPorts(3306);
        $this->withWait(new WaitForLog('ready for connections'));
        $this->withEnvironment('MARIADB_ROOT_PASSWORD', $mysqlRootPassword);
    }

    /**
     *  @deprecated Use constructor instead
     *  Left for backward compatibility
     */
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