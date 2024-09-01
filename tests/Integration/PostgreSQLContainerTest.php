<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Testcontainers\Container\PostgresContainer;

class PostgreSQLContainerTest extends ContainerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::$container = (new PostgresContainer('latest', 'test'))
            ->withPostgresUser('test')
            ->withPostgresDatabase('foo')
            ->start();
    }

    public function testPostgreSQLContainer(): void
    {
        $pdo = new \PDO(
            sprintf('pgsql:host=%s;port=5432;dbname=foo', self::$container->getAddress()),
            'test',
            'test',
        );

        $query = $pdo->query('SELECT datname FROM pg_database');

        $this->assertInstanceOf(\PDOStatement::class, $query);

        $databases = $query->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('foo', $databases);
    }
}
