<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Integration\OldTests;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Testcontainers\Container\Container;
use Testcontainers\Container\MySQLContainer;
use Testcontainers\Container\RedisContainer;
use Testcontainers\Wait\WaitForExec;
use Testcontainers\Wait\WaitForHealthCheck;
use Testcontainers\Wait\WaitForHttp;
use Testcontainers\Wait\WaitForLog;
use Testcontainers\Wait\WaitForTcpPortOpen;

/**
 * Old test classes kept to check backward compatibility
 */
class WaitStrategyTest extends TestCase
{
    //TODO: remove after check
    protected function setUp(): void
    {
        $this->markTestIncomplete();
    }

    public function testWaitForExec(): void
    {
        $container = MySQLContainer::make()
            ->withEnvironment('MYSQL_ROOT_PASSWORD', 'root')
            ->withWait(
                new WaitForExec([
                    'mysqladmin', 'ping',
                    '-h', '127.0.0.1',
                ])
            );

        $container->run();

        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=3306', $container->getAddress()),
            'root',
            'root'
        );

        $query = $pdo->query('select version()');

        $this->assertInstanceOf(\PDOStatement::class, $query);

        $version = $query->fetchColumn();

        $this->assertNotEmpty($version);

        $container->stop();
    }

    public function testWaitForLog(): void
    {
        $container = RedisContainer::make()
            ->withWait(new WaitForLog('Ready to accept connections'));

        $container->run();

        $redis = new Client([
            'scheme' => 'tcp',
            'host'   => $container->getAddress(),
            'port'   => 6379,
        ]);

        $redis->set('foo', 'bar');

        $this->assertEquals('bar', $redis->get('foo'));

        $container->stop();

        $this->expectException(ConnectionException::class);

        $redis->get('foo');

        $container->remove();
    }

    public function testWaitForHTTP(): void
    {
        $container = Container::make('nginx:alpine')
            ->withWait(WaitForHttp::make(80));

        $container->run();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf('http://%s:%d', $container->getAddress(), 80));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = (string) curl_exec($ch);

        curl_close($ch);

        $this->assertNotEmpty($response);
    }

    /**
     * @dataProvider provideWaitForTcpPortOpen
     */
    public function testWaitForTcpPortOpen(bool $wait): void
    {
        $container = Container::make('nginx:alpine');

        if ($wait) {
            $container->withWait(WaitForTcpPortOpen::make(80));
        }

        $container->run();

        if ($wait) {
            static::assertIsResource(fsockopen($container->getAddress(), 80), 'Failed to connect to container');
            return;
        }

        $containerId = $container->getId();

        $this->expectExceptionObject(new ContainerNotReadyException($containerId));

        (new WaitForTcpPortOpen(8080))->wait($containerId);
    }

    /**
     * @return array<string, array<bool>>
     */
    public function provideWaitForTcpPortOpen(): array
    {
        return [
            'Can connect to container' => [true],
            'Cannot connect to container' => [false],
        ];
    }

    public function testWaitForHealthCheck(): void
    {
        $container = Container::make('nginx')
            ->withHealthCheckCommand('curl --fail http://localhost')
            ->withPort('80', '80')
            ->withWait(new WaitForHealthCheck());

        $container->run();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, sprintf('http://%s:%d', $container->getAddress(), 80));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        $this->assertNotEmpty($response);
        $this->assertIsString($response);

        $this->assertStringContainsString('Welcome to nginx!', $response);

        $container->stop();
    }
}
