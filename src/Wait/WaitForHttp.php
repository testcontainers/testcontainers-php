<?php

declare(strict_types=1);

namespace Testcontainer\Wait;

use Symfony\Component\Process\Process;
use Testcontainer\Exception\ContainerNotReadyException;

/**
 * @phpstan-import-type ContainerInspect from \Testcontainer\Container\Container
 */
class WaitForHttp implements WaitInterface
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_OPTIONS = 'OPTIONS';


    private string $method = 'GET';
    private string $path = '/';
    private int $statusCode = 200;

    public function __construct(private int $port)
    {
    }

    public static function make(int $port): self
    {
        return new WaitForHttp($port);
    }

    /**
     * @param WaitForHttp::METHOD_* $method
     */
    public function withMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function withPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function withStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function wait(string $id): void
    {
        $process = new Process(['docker', 'inspect', $id]);
        $process->mustRun();

        /** @var ContainerInspect $data */
        $data = json_decode($process->getOutput(), true);

        $ip = $data[0]['NetworkSettings']['IPAddress'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf('http://%s:%d%s', $ip, $this->port, $this->path));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== $this->statusCode) {
            throw new ContainerNotReadyException($id, new \RuntimeException('HTTP status code does not match'));
        }

        curl_close($ch);
    }
}
