<?php

declare(strict_types=1);

namespace Testcontainers\Utils;

use InvalidArgumentException;

/**
 * Docker image name, parsed into registry, repository and version (tag and/or sha256 digest).
 */
final class DockerImageName
{
    private const ALPHA_NUMERIC = '[a-z0-9]+';

    private const SEPARATOR = '([.]|_{1,2}|-+)';

    private const REPO_NAME_PART = self::ALPHA_NUMERIC . '(' . self::SEPARATOR . self::ALPHA_NUMERIC . ')*';

    private const REPO_NAME = '#^' . self::REPO_NAME_PART . '(/' . self::REPO_NAME_PART . ')*$#D';

    private const TAG_REGEX = '/^[A-Za-z0-9_][A-Za-z0-9_.\-]{0,127}$/D';

    private const HASH_REGEX = '/^[0-9a-fA-F]{32,}$/D';

    private const REGISTRY_REGEX = '/^(?:\[[0-9a-fA-F:.]+\]|[^:\/\[\]]+)(?::(\d{1,5}))?$/D';

    private const DIGEST_PREFIX = 'sha256:';

    /**
     * @param string|null $tag Null if no tag is specified, which means 'latest' unless a digest is set.
     * @param string|null $digest The sha256 hash, without the 'sha256:' prefix.
     */
    private function __construct(
        private readonly string $rawName,
        private readonly string $registry,
        private readonly string $repository,
        private readonly ?string $tag,
        private readonly ?string $digest,
    ) {
    }

    /**
     * Parses a docker image name from a provided string.
     *
     * @param string $fullImageName in standard Docker format, e.g. 'name:tag',
     *                              'some.registry/path/name:tag',
     *                              'some.registry/path/name@sha256:abcdef...', etc.
     */
    public static function parse(string $fullImageName): self
    {
        $slashIndex = strpos($fullImageName, '/');

        if ($slashIndex === false || !self::isRegistry(substr($fullImageName, 0, $slashIndex))) {
            $registry = '';
            $remoteName = $fullImageName;
        } else {
            $registry = substr($fullImageName, 0, $slashIndex);
            $remoteName = substr($fullImageName, $slashIndex + 1);
        }

        if (str_contains($remoteName, '@' . self::DIGEST_PREFIX)) {
            [$beforeDigest, $digest] = explode('@' . self::DIGEST_PREFIX, $remoteName, 2);
            if (str_contains($beforeDigest, ':')) {
                [$repository, $tag] = explode(':', $beforeDigest, 2);

                return new self($fullImageName, $registry, $repository, $tag, $digest);
            }

            return new self($fullImageName, $registry, $beforeDigest, null, $digest);
        }

        if (str_contains($remoteName, ':')) {
            [$repository, $tag] = explode(':', $remoteName, 2);

            return new self($fullImageName, $registry, $repository, $tag, null);
        }

        return new self($fullImageName, $registry, $remoteName, null, null);
    }

    public function getRegistry(): string
    {
        return $this->registry;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    /**
     * @return string the unversioned (non 'tag') part of this name
     */
    public function getUnversionedPart(): string
    {
        if ($this->registry !== '') {
            return $this->registry . '/' . $this->repository;
        }

        return $this->repository;
    }

    /**
     * @return string the versioned part of this name (tag or sha256). When both tag and digest are present,
     *                the tag is returned.
     */
    public function getVersionPart(): string
    {
        if ($this->tag !== null) {
            return $this->tag;
        }

        return $this->getDigest() ?? 'latest';
    }

    /**
     * @return string|null the sha256 digest if present, or null
     */
    public function getDigest(): ?string
    {
        return $this->digest !== null ? self::DIGEST_PREFIX . $this->digest : null;
    }

    /**
     * @return string canonical name for the image
     */
    public function asCanonicalNameString(): string
    {
        $digest = $this->getDigest();
        if ($digest === null) {
            return $this->getUnversionedPart() . ':' . $this->getVersionPart();
        }

        if ($this->tag !== null) {
            return $this->getUnversionedPart() . ':' . $this->tag . '@' . $digest;
        }

        return $this->getUnversionedPart() . '@' . $digest;
    }

    public function __toString(): string
    {
        return $this->asCanonicalNameString();
    }

    /**
     * Is the image name valid?
     *
     * @throws InvalidArgumentException if not valid
     */
    public function assertValid(): void
    {
        if ($this->registry !== '' && !self::isValidHostAndPort($this->registry)) {
            throw new InvalidArgumentException("{$this->registry} is not a valid registry address (in {$this->rawName})");
        }

        if (preg_match(self::REPO_NAME, $this->repository) !== 1) {
            throw new InvalidArgumentException("{$this->repository} is not a valid Docker image name (in {$this->rawName})");
        }

        $isValidTag = $this->tag === null || preg_match(self::TAG_REGEX, $this->tag) === 1;
        $isValidDigest = $this->digest === null || preg_match(self::HASH_REGEX, $this->digest) === 1;
        if (!$isValidTag || !$isValidDigest) {
            throw new InvalidArgumentException("{$this->getVersionPart()} is not a valid image versioning identifier (in {$this->rawName})");
        }
    }

    /**
     * @param string $newTag version tag for the copy to use
     * @return self an immutable copy of this DockerImageName with the new version tag
     */
    public function withTag(string $newTag): self
    {
        return new self($this->rawName, $this->registry, $this->repository, $newTag, null);
    }

    public function withRegistry(string $registry): self
    {
        return new self($this->rawName, $registry, $this->repository, $this->tag, $this->digest);
    }

    public function withRepository(string $repository): self
    {
        return new self($this->rawName, $this->registry, $repository, $this->tag, $this->digest);
    }

    /**
     * The part before the first slash is a registry if it looks like a host: contains a dot or a port, or is localhost.
     */
    private static function isRegistry(string $candidate): bool
    {
        return str_contains($candidate, '.') || str_contains($candidate, ':') || $candidate === 'localhost';
    }

    private static function isValidHostAndPort(string $registry): bool
    {
        if (preg_match(self::REGISTRY_REGEX, $registry, $matches) !== 1) {
            return false;
        }

        return !isset($matches[1]) || (int) $matches[1] <= 65535;
    }
}
