<?php

declare(strict_types=1);

namespace Tests\Support;

use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Support\Filesystem;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class FilesystemTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-fs');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testExistsAndSize(): void
    {
        $file = $this->root . '/sample.txt';
        file_put_contents($file, 'conteudo');

        self::assertTrue(Filesystem::exists($file));
        self::assertSame(8, Filesystem::size($file));
        self::assertFalse(Filesystem::exists($this->root . '/nao-existe.txt'));
    }

    public function testEnsureDirectoryExistsCreatesNestedDirectory(): void
    {
        $nested = $this->root . '/a/b/c';

        Filesystem::ensureDirectoryExists($nested);

        self::assertDirectoryExists($nested);
    }

    public function testMoveRelocatesFileAndCreatesDestinationDirectory(): void
    {
        $source = $this->root . '/origem.txt';
        $destination = $this->root . '/novo/destino.txt';
        file_put_contents($source, 'dado');

        Filesystem::move($source, $destination);

        self::assertFileDoesNotExist($source);
        self::assertFileExists($destination);
        self::assertSame('dado', file_get_contents($destination));
    }

    public function testSizeThrowsForMissingFile(): void
    {
        $this->expectException(CollectorException::class);

        Filesystem::size($this->root . '/inexistente.txt');
    }
}
