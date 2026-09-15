<?php

declare(strict_types=1);

namespace Testcontainers\Docker\Model;

class Mount
{
    private ?string $type = null;
    private ?string $source = null;
    private ?string $target = null;

    /**
     * @param array<mixed> $data
     */
    public function __construct(array $data = [])
    {
        $type = $data['type'] ?? $data['Type'] ?? null;
        $this->type = is_string($type) ? $type : null;

        $source = $data['source'] ?? $data['Source'] ?? null;
        $this->source = is_string($source) ? $source : null;

        $target = $data['target'] ?? $data['Target'] ?? null;
        $this->target = is_string($target) ? $target : null;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function setTarget(string $target): self
    {
        $this->target = $target;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $payload = [];
        if ($this->type !== null) {
            $payload['Type'] = $this->type;
        }
        if ($this->source !== null) {
            $payload['Source'] = $this->source;
        }
        if ($this->target !== null) {
            $payload['Target'] = $this->target;
        }

        return $payload;
    }
}
