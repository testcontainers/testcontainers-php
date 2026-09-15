<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\Utils;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Testcontainers\Utils\TarBuilder;

/**
 * @covers \Testcontainers\Utils\TarBuilder
 */
class TarBuilderTest extends TestCase
{
    private const TEST_CONTENT = 'hello world';
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/tarbuilder_test_' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectoryRecursively($this->tempDir);
        parent::tearDown();
    }

    public function testShouldAddSingleFile(): void
    {
        $sourceFile = $this->tempDir . '/file.txt';
        file_put_contents($sourceFile, self::TEST_CONTENT);

        $tarBuilder = new TarBuilder();
        $tarBuilder->addFile($sourceFile, 'mydir/file_in_tar.txt', 0o644);

        $tarPath = $tarBuilder->buildTarArchive();

        $this->assertFileExists($tarPath, 'Tar file was not created');

        $extractDir = $this->tempDir . '/extract';
        mkdir($extractDir);
        $this->extractTar($tarPath, $extractDir);

        $extractedFile = $extractDir . '/mydir/file_in_tar.txt';
        $this->assertFileExists($extractedFile);
        $this->assertSame(self::TEST_CONTENT, file_get_contents($extractedFile));

        $this->assertPosixMode('644', $extractedFile, 'Expected file mode 0644');
    }

    public function testShouldAddDirectoryRecursively(): void
    {
        $localDir = $this->tempDir . '/localdir';
        mkdir($localDir);
        file_put_contents($localDir . '/one.txt', 'file1');
        file_put_contents($localDir . '/two.txt', 'file2');

        $tarBuilder = new TarBuilder();
        $tarBuilder->addDirectory($localDir, 'mydir', 0o755);
        $tarPath = $tarBuilder->buildTarArchive();

        $this->assertFileExists($tarPath);

        $extractDir = $this->tempDir . '/extractdir';
        mkdir($extractDir);
        $this->extractTar($tarPath, $extractDir);

        $oneExtracted = $extractDir . '/mydir/one.txt';
        $twoExtracted = $extractDir . '/mydir/two.txt';
        $this->assertFileExists($oneExtracted);
        $this->assertFileExists($twoExtracted);

        $this->assertSame('file1', file_get_contents($oneExtracted));
        $this->assertSame('file2', file_get_contents($twoExtracted));

        $this->assertPosixMode('755', $extractDir . '/mydir', 'Expected directory mode 0755');
    }

    public function testShouldAddInlineContent(): void
    {
        $content = "Inline content test\nLine2";

        $tarBuilder = new TarBuilder();
        $tarBuilder->addContent($content, 'some/path/inline.txt', 0o777);
        $tarPath = $tarBuilder->buildTarArchive();

        $this->assertFileExists($tarPath);

        $extractDir = $this->tempDir . '/extractContent';
        mkdir($extractDir);
        $this->extractTar($tarPath, $extractDir);

        $inlineExtracted = $extractDir . '/some/path/inline.txt';
        $this->assertFileExists($inlineExtracted);
        $this->assertSame($content, file_get_contents($inlineExtracted));

        $this->assertPosixMode('777', $inlineExtracted, 'Expected file mode 0777');
    }

    public function testShouldFailOnInvalidFilePath(): void
    {
        $tarBuilder = new TarBuilder();
        $this->expectException(InvalidArgumentException::class);
        $tarBuilder->addFile('/some/nonexistent/file', 'target.txt');
    }

    public function testShouldFailOnEmptyTarget(): void
    {
        $localFile = $this->tempDir . '/somefile.txt';
        file_put_contents($localFile, 'abc');

        $tarBuilder = new TarBuilder();
        $this->expectException(InvalidArgumentException::class);
        $tarBuilder->addFile($localFile, '');
    }

    public function testShouldFailOnInvalidMode(): void
    {
        $localFile = $this->tempDir . '/somefile.txt';
        file_put_contents($localFile, 'abc');

        $tarBuilder = new TarBuilder();
        $this->expectException(InvalidArgumentException::class);
        $tarBuilder->addFile($localFile, 'target.txt', 9999);
    }

    public function testShouldCreateEmptyTarIfNoItemsAdded(): void
    {
        $tarBuilder = new TarBuilder();

        $tarPath = $tarBuilder->buildTarArchive();
        $this->assertFileExists($tarPath);

        $extractDir = $this->tempDir . '/extractEmpty';
        mkdir($extractDir);
        $this->extractTar($tarPath, $extractDir);

        $scanned = array_diff(scandir($extractDir) ?: [], ['.', '..']);
        $this->assertCount(0, $scanned, 'Expected empty directory');
    }

    public function testShouldClearItems(): void
    {
        $tarBuilder = new TarBuilder();
        $localFile = $this->tempDir . '/somefile.txt';
        file_put_contents($localFile, 'abc');
        $tarBuilder->addFile($localFile, 'test.txt');

        $tarBuilder->clear();

        $tarPath = $tarBuilder->buildTarArchive();
        $this->assertFileExists($tarPath);

        $extractDir = $this->tempDir . '/extractCleared';
        mkdir($extractDir);
        $this->extractTar($tarPath, $extractDir);

        $scanned = array_diff(scandir($extractDir) ?: [], ['.', '..']);
        $this->assertCount(0, $scanned, 'Expected no files after clear()');
    }

    /**
     * Asserts the Unix permission bits of an extracted path.
     *
     * Windows filesystems cannot store Unix modes, so chmod() and fileperms()
     * only reflect the read-only flag there; the check is skipped on Windows.
     */
    private function assertPosixMode(string $expected, string $path, string $message): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }

        $perms = substr(sprintf('%o', fileperms($path)), -3);
        $this->assertSame($expected, $perms, $message);
    }

    /**
     * Helper function to extract a .tar for verification.
     */
    private function extractTar(string $tarPath, string $destination): void
    {
        $cmd = sprintf(
            'tar -xpf %s -C %s 2>&1',
            escapeshellarg($tarPath),
            escapeshellarg($destination)
        );

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            $errorText = implode("\n", $output);
            throw new RuntimeException("Failed to extract tar:\n{$errorText}");
        }
    }

    /**
     * Recursively remove directory.
     */
    private function removeDirectoryRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        /** @var RecursiveIteratorIterator<RecursiveDirectoryIterator> $items */
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }
        rmdir($path);
    }
}
