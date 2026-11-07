<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\Utils;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Testcontainers\Utils\DockerImageName;

/**
 * @covers \Testcontainers\Utils\DockerImageName
 */
class DockerImageNameTest extends TestCase
{
    private const DIGEST = 'sha256:1234abcd1234abcd1234abcd1234abcd';

    /**
     * @return array<string, array{string}>
     */
    public static function validNames(): array
    {
        $names = [
            'myname:latest',
            'repo/my-name:1.0',
            'registry.foo.com:1234/my-name:1.0',
            'registry.foo.com/my-name:1.0',
            'registry.foo.com:1234/repo_here/my-name:1.0',
            'registry.foo.com:1234/repo-here/my-name@' . self::DIGEST,
            'registry.foo.com:1234/my-name@' . self::DIGEST,
            'myname:latest@' . self::DIGEST,
            'registry.foo.com:1234/repo-here/my-name:1.0@' . self::DIGEST,
            '1.2.3.4/my-name:1.0',
            '1.2.3.4:1234/my-name:1.0',
            '1.2.3.4/repo-here/my-name:1.0',
            '1.2.3.4:1234/repo-here/my-name:1.0',
        ];

        return array_combine($names, array_map(static fn (string $name): array => [$name], $names));
    }

    /**
     * @dataProvider validNames
     */
    public function testValidNameAccepted(string $imageName): void
    {
        $this->expectNotToPerformAssertions();

        DockerImageName::parse($imageName)->assertValid();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        $names = [
            ':latest',
            '/myname:latest',
            '/myname@sha256:latest',
            '/myname@sha256:gggggggggggggggggggggggggggggggg',
            'repo:notaport/myname:latest',
            'repo/image:tag:extra@' . self::DIGEST,
        ];

        return array_combine($names, array_map(static fn (string $name): array => [$name], $names));
    }

    /**
     * @dataProvider invalidNames
     */
    public function testInvalidNameRejected(string $imageName): void
    {
        $this->expectException(InvalidArgumentException::class);

        DockerImageName::parse($imageName)->assertValid();
    }

    /**
     * @return list<array{string, string, string, string, string|null}>
     */
    public static function parsingCases(): array
    {
        return [
            ['', '', 'myname', ':', null],
            ['', '', 'myname', ':', 'latest'],
            ['', '', 'repo/myname', ':', null],
            ['', '', 'repo/myname', ':', 'latest'],
            ['registry.foo.com:1234', '/', 'my-name', ':', null],
            ['registry.foo.com:1234', '/', 'my-name', ':', '1.0'],
            ['registry.foo.com', '/', 'my-name', ':', '1.0'],
            ['registry.foo.com:1234', '/', 'repo_here/my-name', ':', null],
            ['registry.foo.com:1234', '/', 'repo_here/my-name', ':', '1.0'],
            ['1.2.3.4:1234', '/', 'repo_here/my-name', ':', null],
            ['1.2.3.4:1234', '/', 'repo_here/my-name', ':', '1.0'],
            ['1.2.3.4:1234', '/', 'my-name', ':', null],
            ['1.2.3.4:1234', '/', 'my-name', ':', '1.0'],
            ['', '', 'myname', '@', self::DIGEST],
            ['', '', 'repo/myname', '@', self::DIGEST],
            ['registry.foo.com:1234', '/', 'repo-here/my-name', '@', self::DIGEST],
            ['registry.foo.com:1234', '/', 'my-name', '@', self::DIGEST],
            ['1.2.3.4', '/', 'my-name', '@', self::DIGEST],
            ['1.2.3.4:1234', '/', 'my-name', '@', self::DIGEST],
            ['localhost', '/', 'my-name', ':', '1.0'],
            ['localhost:5000', '/', 'repo/my-name', ':', null],
        ];
    }

    /**
     * @dataProvider parsingCases
     */
    public function testParsing(
        string $registry,
        string $registrySeparator,
        string $repository,
        string $versionSeparator,
        ?string $version
    ): void {
        $unversionedPart = $registry . $registrySeparator . $repository;
        $combined = $version !== null ? $unversionedPart . $versionSeparator . $version : $unversionedPart;
        $canonicalName = $version !== null ? $combined : $unversionedPart . ':latest';

        $imageName = DockerImageName::parse($combined);

        self::assertSame($registry, $imageName->getRegistry(), "$combined has registry address: $registry");
        self::assertSame($repository, $imageName->getRepository(), "$combined has repository: $repository");
        self::assertSame($unversionedPart, $imageName->getUnversionedPart(), "$combined has unversioned part: $unversionedPart");
        self::assertSame($version ?? 'latest', $imageName->getVersionPart(), "$combined has version part");
        self::assertSame($canonicalName, $imageName->asCanonicalNameString(), "$combined has canonical name: $canonicalName");
    }

    public function testTagAndDigestStripsTagFromRepository(): void
    {
        $imageName = DockerImageName::parse('myname:latest@' . self::DIGEST);

        self::assertSame('', $imageName->getRegistry());
        self::assertSame('myname', $imageName->getUnversionedPart());
        self::assertSame('latest', $imageName->getVersionPart());
        self::assertSame(self::DIGEST, $imageName->getDigest());
        self::assertSame('myname:latest@' . self::DIGEST, $imageName->asCanonicalNameString());
    }

    public function testTagAndDigestWithRepoPath(): void
    {
        $imageName = DockerImageName::parse('repo/myname:1.0@' . self::DIGEST);

        self::assertSame('', $imageName->getRegistry());
        self::assertSame('repo/myname', $imageName->getUnversionedPart());
        self::assertSame('1.0', $imageName->getVersionPart());
        self::assertSame(self::DIGEST, $imageName->getDigest());
        self::assertSame('repo/myname:1.0@' . self::DIGEST, $imageName->asCanonicalNameString());
    }

    public function testTagAndDigestWithRegistry(): void
    {
        $imageName = DockerImageName::parse('registry.foo.com:1234/repo-here/my-name:1.0@' . self::DIGEST);

        self::assertSame('registry.foo.com:1234', $imageName->getRegistry());
        self::assertSame('registry.foo.com:1234/repo-here/my-name', $imageName->getUnversionedPart());
        self::assertSame('1.0', $imageName->getVersionPart());
        self::assertSame(self::DIGEST, $imageName->getDigest());
        self::assertSame(
            'registry.foo.com:1234/repo-here/my-name:1.0@' . self::DIGEST,
            $imageName->asCanonicalNameString()
        );
    }

    public function testDigestOnlyHasNoTag(): void
    {
        $imageName = DockerImageName::parse('myname@' . self::DIGEST);

        self::assertSame(self::DIGEST, $imageName->getVersionPart());
        self::assertSame(self::DIGEST, $imageName->getDigest());
    }

    public function testTagOnlyHasNoDigest(): void
    {
        self::assertNull(DockerImageName::parse('myname:1.0')->getDigest());
    }

    public function testWithTagReplacesTagAndDigest(): void
    {
        $imageName = DockerImageName::parse('registry.foo.com/my-name:1.0@' . self::DIGEST)->withTag('2.0');

        self::assertSame('registry.foo.com/my-name:2.0', $imageName->asCanonicalNameString());
        self::assertNull($imageName->getDigest());
    }

    public function testWithRegistryAndRepositoryKeepVersion(): void
    {
        $imageName = DockerImageName::parse('my-name:1.0@' . self::DIGEST)
            ->withRegistry('registry.foo.com')
            ->withRepository('repo/other-name');

        self::assertSame('registry.foo.com/repo/other-name:1.0@' . self::DIGEST, (string) $imageName);
    }
}
