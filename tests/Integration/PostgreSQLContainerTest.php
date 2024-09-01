<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Testcontainers\Container\PostgresContainer;

class PostgreSQLContainerTest extends ContainerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::$container = (new PostgresContainer())
            ->withPostgresUser('bar')
            ->withPostgresDatabase('foo')
            ->start();
    }

    public function testPostgreSQLContainer(): void
    {
        $pdo = new \PDO(
            'pgsql:host=127.0.0.1;port=5432;dbname=foo',
            'bar',
            'test',
        );

        $query = $pdo->query('SELECT datname FROM pg_database');

        $this->assertInstanceOf(\PDOStatement::class, $query);

        $databases = $query->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('foo', $databases);
    }
}
