<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class HealthConfig
{
    /** @var array<int, string>|null */
    private ?array $test = null;
    private ?int $interval = null;
    private ?int $timeout = null;
    private ?int $retries = null;
    private ?int $startPeriod = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        if (isset($data['Test']) && is_array($data['Test'])) {
            $this->test = $data['Test'];
        }
        if (isset($data['Interval']) && (is_int($data['Interval']) || is_float($data['Interval']))) {
            $this->interval = (int) $data['Interval'];
        }
        if (isset($data['Timeout']) && (is_int($data['Timeout']) || is_float($data['Timeout']))) {
            $this->timeout = (int) $data['Timeout'];
        }
        if (isset($data['Retries']) && (is_int($data['Retries']) || is_float($data['Retries']))) {
            $this->retries = (int) $data['Retries'];
        }
        if (isset($data['StartPeriod']) && (is_int($data['StartPeriod']) || is_float($data['StartPeriod']))) {
            $this->startPeriod = (int) $data['StartPeriod'];
        }
    }

    /**
     * @param array<int, string> $test
     */
    public function setTest(array $test): self
    {
        $this->test = $test;

        return $this;
    }

    public function setInterval(int $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function setRetries(int $retries): self
    {
        $this->retries = $retries;

        return $this;
    }

    public function setStartPeriod(int $startPeriod): self
    {
        $this->startPeriod = $startPeriod;

        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getTest(): ?array
    {
        return $this->test;
    }

    public function getInterval(): ?int
    {
        return $this->interval;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getRetries(): ?int
    {
        return $this->retries;
    }

    public function getStartPeriod(): ?int
    {
        return $this->startPeriod;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];
        if ($this->test !== null) {
            $payload['Test'] = $this->test;
        }
        if ($this->interval !== null) {
            $payload['Interval'] = $this->interval;
        }
        if ($this->timeout !== null) {
            $payload['Timeout'] = $this->timeout;
        }
        if ($this->retries !== null) {
            $payload['Retries'] = $this->retries;
        }
        if ($this->startPeriod !== null) {
            $payload['StartPeriod'] = $this->startPeriod;
        }

        return $payload;
    }
}
