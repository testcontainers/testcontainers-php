<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration;

use Testcontainers\Container\MySQLContainer;

class MySQLContainerTest extends ContainerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::$container = (new MySQLContainer())
            ->withMySQLDatabase('foo')
            ->withMySQLUser('bar', 'baz')
            ->start();
    }

    public function testMySQLContainer(): void
    {
        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=3306', '127.0.0.1'),
            'bar',
            'baz',
        );

        $query = $pdo->query('SHOW databases');

        $this->assertInstanceOf(\PDOStatement::class, $query);

        $databases = $query->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('foo', $databases);
    }
}
