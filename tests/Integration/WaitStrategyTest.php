<?php

declare(strict_types=1);

namespace Testcontainer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Testcontainer\Container\Container;
use Testcontainer\Wait\WaitForExec;
use Testcontainer\Wait\WaitForHealthCheck;
use Testcontainer\Wait\WaitForHttp;
use Testcontainer\Wait\WaitForLog;

class WaitStrategyTest extends TestCase
{
    public function testWaitForExec(): void
    {
        $called = false;

        $container = Container::make('mysql')
            ->withEnvironment('MYSQL_ROOT_PASSWORD', 'root')
            ->withWait(new WaitForExec(['mysqladmin', 'ping', '-h', '127.0.0.1'], function (Process $process) use (&$called) {
                $called = true;
            }));

        $container->run();

        $this->assertTrue($called, 'Wait function was not called');
        unset($called);

        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=3306', $container->getAddress()),
            'root',
            'root'
        );

        $query = $pdo->query('select version()');

        $this->assertInstanceOf(\PDOStatement::class, $query);

        $version = $query->fetchColumn();

        $this->assertNotEmpty($version);
    }

    public function testWaitForLog(): void
    {
        $container = Container::make('redis:6.2.5')
            ->withWait(new WaitForLog('Ready to accept connections'));

        $container->run();

        $redis = new \Redis();
        $redis->connect($container->getAddress(), 6379, 0.001);

        $redis->set('foo', 'bar');

        $this->assertEquals('bar', $redis->get('foo'));

        $container->stop();

        $this->expectException(\RedisException::class);

        $redis->get('foo');

        $container->remove();
    }

    public function testWaitForHTTP(): void
    {
        $container = Container::make('opensearchproject/opensearch')
            ->withEnvironment('discovery.type', 'single-node')
            ->withEnvironment('plugins.security.disabled', 'true')
            ->withWait(WaitForHttp::make(9200));

        $container->run();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf('http://%s:%d', $container->getAddress(), 9200));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = (string) curl_exec($ch);

        $this->assertNotEmpty($response);

        /** @var array{cluster_name: string} $data */
        $data = json_decode($response, true);

        $this->assertArrayHasKey('cluster_name', $data);

        $this->assertEquals('docker-cluster', $data['cluster_name']);
    }

    public function testWaitForHealthCheck(): void
    {
        $container = Container::make('nginx')
            ->withHealthCheckCommand('curl --fail http://localhost')
            ->withWait(new WaitForHealthCheck());

        $container->run();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, sprintf('http://%s:%d', $container->getAddress(), 80));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        $this->assertNotEmpty($response);
        $this->assertIsString($response);

        $this->assertStringContainsString('Welcome to nginx!', $response);
    }
}
