<?php

declare(strict_types=1);

namespace Tests\Collectors\Providers\Investing;

use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\Providers\Investing\InvestingProvider;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class InvestingProviderTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-investing-provider');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testThrowsWhenIncomingFolderDoesNotExist(): void
    {
        $provider = new InvestingProvider($this->root . '/nao-existe');

        $this->expectException(CollectorException::class);

        $provider->collect();
    }

    public function testThrowsWhenIncomingFolderIsEmpty(): void
    {
        $incoming = $this->root . '/incoming';
        mkdir($incoming, 0775, true);
        touch($incoming . '/.gitkeep');

        $provider = new InvestingProvider($incoming);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }

    public function testCollectsTheMostRecentlyModifiedFile(): void
    {
        $incoming = $this->root . '/incoming';
        mkdir($incoming, 0775, true);

        file_put_contents($incoming . '/planilha-antiga.xlsx', 'antiga');
        touch($incoming . '/planilha-antiga.xlsx', time() - 100);

        file_put_contents($incoming . '/planilha-recente.xlsx', 'recente');
        touch($incoming . '/planilha-recente.xlsx', time());

        $provider = new InvestingProvider($incoming);
        $file = $provider->collect();

        self::assertSame($incoming . '/planilha-recente.xlsx', $file->path);
        self::assertSame('investing', $file->provider);
        self::assertSame('planilha-recente.xlsx', $file->originalFilename);
    }
}
