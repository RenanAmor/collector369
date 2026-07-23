<?php

declare(strict_types=1);

namespace Tests\Transport;

use Collector369\Logging\Logger;
use Collector369\Transport\ProductionTransport;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;
use Tests\Transport\Ftp\FakeFtpClient;

final class ProductionTransportTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $outputPath;

    private Logger $logger;

    protected function setUp(): void
    {
        $this->outputPath = $this->makeTempDirectory('collector369-transport-output');
        $this->logger = new Logger($this->makeTempDirectory('collector369-transport-logs'), 'error');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputPath);
    }

    public function testTransportsLatestFileWhenRemoteIsEmpty(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'conteudo-real');

        $ftp = new FakeFtpClient();
        $transport = $this->makeTransport($ftp);

        $outcome = $transport->run();

        self::assertTrue($outcome->connected);
        self::assertCount(1, $outcome->results);
        self::assertSame('transported', $outcome->results[0]->status);
        self::assertSame('conteudo-real', $ftp->files['/investing/investing_2026-07-23_100000.xlsx'] ?? null);
        self::assertArrayNotHasKey('/investing/.tmp_investing_2026-07-23_100000.xlsx', $ftp->files);
    }

    public function testSkipsUploadWhenRemoteAlreadyHasIdenticalContent(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'conteudo-real');

        $ftp = new FakeFtpClient();
        $ftp->files['/investing/investing_2026-07-23_100000.xlsx'] = 'conteudo-real';

        $outcome = $this->makeTransport($ftp)->run();

        self::assertSame('already_current', $outcome->results[0]->status);
        self::assertSame(0, $ftp->putCalls);
    }

    public function testConflictWhenSameNameHasDifferentContentAndDoesNotOverwrite(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'conteudo-novo');

        $ftp = new FakeFtpClient();
        $ftp->files['/investing/investing_2026-07-23_100000.xlsx'] = 'conteudo-antigo-diferente';

        $outcome = $this->makeTransport($ftp)->run();

        self::assertSame('conflict', $outcome->results[0]->status);
        self::assertSame(0, $ftp->putCalls);
        self::assertSame(
            'conteudo-antigo-diferente',
            $ftp->files['/investing/investing_2026-07-23_100000.xlsx'],
        );
    }

    public function testConflictWhenSameNameHasSameSizeButDifferentContent(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'AAAAAAAAAA');

        $ftp = new FakeFtpClient();
        $ftp->files['/investing/investing_2026-07-23_100000.xlsx'] = 'BBBBBBBBBB';

        $outcome = $this->makeTransport($ftp)->run();

        self::assertSame('conflict', $outcome->results[0]->status);
        self::assertSame('BBBBBBBBBB', $ftp->files['/investing/investing_2026-07-23_100000.xlsx']);
    }

    public function testRemovesOrphanTmpFileBeforeUploading(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'conteudo-real');

        $ftp = new FakeFtpClient();
        $ftp->files['/investing/.tmp_investing_2026-07-23_100000.xlsx'] = 'lixo-de-tentativa-anterior';

        $outcome = $this->makeTransport($ftp)->run();

        self::assertSame('transported', $outcome->results[0]->status);
        self::assertContains('/investing/.tmp_investing_2026-07-23_100000.xlsx', $ftp->deletedPaths);
        self::assertSame('conteudo-real', $ftp->files['/investing/investing_2026-07-23_100000.xlsx']);
    }

    public function testProducesErrorAndCleansTmpWhenUploadIsCorrupted(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'conteudo-real');

        $ftp = new FakeFtpClient(corruptNextPut: true);

        $outcome = $this->makeTransport($ftp)->run();

        self::assertSame('error', $outcome->results[0]->status);
        self::assertArrayNotHasKey('/investing/investing_2026-07-23_100000.xlsx', $ftp->files);
        self::assertArrayNotHasKey('/investing/.tmp_investing_2026-07-23_100000.xlsx', $ftp->files);
    }

    public function testReturnsNothingToTransportWhenProviderHasNoFiles(): void
    {
        mkdir($this->outputPath . '/twelvedata', 0775, true);

        $outcome = $this->makeTransport(new FakeFtpClient())->run();

        self::assertSame('nothing_to_transport', $outcome->results[0]->status);
    }

    public function testReturnsEmptyOutcomeWhenOutputPathHasNoProviders(): void
    {
        $outcome = $this->makeTransport(new FakeFtpClient())->run();

        self::assertTrue($outcome->connected);
        self::assertSame([], $outcome->results);
    }

    public function testRetriesConnectionAfterTransientFailureThenSucceeds(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'conteudo-real');

        $ftp = new FakeFtpClient(connectSucceedsOnAttempt: 2);
        $outcome = $this->makeTransport($ftp, retryDelaysSeconds: [0, 0])->run();

        self::assertTrue($outcome->connected);
        self::assertSame(2, $ftp->connectAttempts);
        self::assertSame('transported', $outcome->results[0]->status);
    }

    public function testReportsTotalFailureWhenConnectionNeverSucceeds(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'conteudo-real');

        $ftp = new FakeFtpClient(connectSucceedsOnAttempt: 99);
        $outcome = $this->makeTransport($ftp, retryDelaysSeconds: [0, 0])->run();

        self::assertFalse($outcome->connected);
        self::assertSame([], $outcome->results);
        self::assertSame(3, $ftp->connectAttempts);
    }

    public function testOnlyProviderFilterRestrictsProcessing(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'a');
        $this->writeLocalFile('twelvedata', 'twelvedata_2026-07-23_100000.xlsx', 'b');

        $ftp = new FakeFtpClient();
        $outcome = $this->makeTransport($ftp)->run('investing');

        self::assertCount(1, $outcome->results);
        self::assertSame('investing', $outcome->results[0]->provider);
    }

    public function testTransportsLatestFileWhenMultipleFilesExistForProvider(): void
    {
        $this->writeLocalFile('investing', 'investing_2026-07-23_090000.xlsx', 'antigo');
        $this->writeLocalFile('investing', 'investing_2026-07-23_100000.xlsx', 'mais-recente');

        $ftp = new FakeFtpClient();
        $outcome = $this->makeTransport($ftp)->run();

        self::assertSame(
            'mais-recente',
            $ftp->files['/investing/investing_2026-07-23_100000.xlsx'] ?? null,
        );
        self::assertArrayNotHasKey('/investing/investing_2026-07-23_090000.xlsx', $ftp->files);
    }

    private function writeLocalFile(string $provider, string $filename, string $content): void
    {
        $dir = $this->outputPath . '/' . $provider;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($dir . '/' . $filename, $content);
    }

    /**
     * @param array<int, int> $retryDelaysSeconds
     */
    private function makeTransport(FakeFtpClient $ftp, array $retryDelaysSeconds = [0, 0]): ProductionTransport
    {
        return new ProductionTransport(
            outputPath: $this->outputPath,
            ftp: $ftp,
            host: 'ftp.investimentos369.com',
            port: 21,
            user: 'user',
            password: 'secret',
            remoteRoot: '/',
            logger: $this->logger,
            connectTimeoutSeconds: 1,
            retryDelaysSeconds: $retryDelaysSeconds,
        );
    }
}
