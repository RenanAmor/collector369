<?php

declare(strict_types=1);

namespace Tests\Collectors\Storage;

use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Storage\CollectorStorage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class CollectorStorageTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-storage');
        mkdir($this->root . '/staging', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testStoresFileWithDeterministicNameUnderOutputPath(): void
    {
        $stagedPath = $this->root . '/staging/planilha_original.xlsx';
        file_put_contents($stagedPath, 'conteudo');

        $file = new CollectedFile(
            path: $stagedPath,
            provider: 'investing',
            collectedAt: new DateTimeImmutable('2026-07-21 21:30:00'),
            originalFilename: 'planilha_original.xlsx',
        );

        $storage = new CollectorStorage($this->root . '/output');
        $stored = $storage->store($file);

        $expectedPath = $this->root . '/output/investing/investing_2026-07-21_213000.xlsx';

        self::assertSame($expectedPath, $stored->path);
        self::assertFileExists($expectedPath);
        self::assertFileDoesNotExist($stagedPath);
        self::assertSame('conteudo', file_get_contents($expectedPath));
    }
}
