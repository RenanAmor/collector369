<?php

declare(strict_types=1);

namespace Tests\Collectors\Providers\B3;

use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\Providers\B3\B3BulletinClient;
use Collector369\Collectors\Providers\B3\B3BulletinTableParser;
use Collector369\Collectors\Providers\B3\B3DolfutProvider;
use Collector369\Collectors\Providers\B3\DolContractResolver;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class B3DolfutProviderTest extends TestCase
{
    use InteractsWithTempDirectories;

    private const REAL_DOLQ26_LINE =
        'DOLQ26       BRBMEFDOL835  FINANCIAL  5.178,0000   5.137,0000  5.184,5000  5.154,7380  5.142,5000      -0,64     5.146,6620              -  5.175,6930                -  -29,0310  -1.451,55   5.139,0000    5.145,5000    12.805       174.530       44.982.823,12';

    private string $cachePath;
    private string $stagingPath;

    protected function setUp(): void
    {
        $this->cachePath = $this->makeTempDirectory('collector369-b3dolfut-cache');
        $this->stagingPath = $this->makeTempDirectory('collector369-b3dolfut-staging');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cachePath);
        $this->removeDirectory($this->stagingPath);
    }

    /**
     * @param list<string> $downloadedUrls
     */
    private function buildClient(array &$downloadedUrls, bool $todaysBulletinExists = true): B3BulletinClient
    {
        $urlExists = static function (string $url) use ($todaysBulletinExists): bool {
            // Simula boletim de 2026-07-09 disponível; datas depois disso "ainda não publicadas".
            return $todaysBulletinExists || str_contains($url, '2026-07-09');
        };

        $download = function (string $url, string $destPath) use (&$downloadedUrls): void {
            $downloadedUrls[] = $url;
            file_put_contents($destPath, 'fake-pdf-bytes');
        };

        $extractTable = fn (string $pdfPath): string => self::REAL_DOLQ26_LINE;

        return new B3BulletinClient($urlExists, $download, $extractTable);
    }

    private function buildProvider(B3BulletinClient $client, ?callable $clock = null): B3DolfutProvider
    {
        return new B3DolfutProvider(
            $client,
            new B3BulletinTableParser(),
            new DolContractResolver(),
            $this->cachePath,
            $this->stagingPath,
            $clock,
        );
    }

    public function testColetaOAjusteDoContratoVigenteEEscreveAPlanilhaPadronizada(): void
    {
        $downloadedUrls = [];
        $client = $this->buildClient($downloadedUrls);
        $clock = fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-09');

        $provider = $this->buildProvider($client, $clock);
        $file = $provider->collect();

        self::assertSame('b3dolfut', $file->provider);
        self::assertFileExists($file->path);
        self::assertCount(1, $downloadedUrls);

        $rows = IOFactory::load($file->path)->getActiveSheet()->toArray();

        self::assertSame(
            ['Símbolo', 'Contrato/Vencimento', 'Preço', 'Variação', 'Variação (%)', 'Data/Hora', 'Fonte', 'Volume'],
            $rows[0],
        );
        self::assertSame('DOLFUT', $rows[1][0]);
        self::assertStringContainsString('DOLQ26', (string) $rows[1][1]);
        self::assertStringContainsString('03/08/2026', (string) $rows[1][1]);
        self::assertStringNotContainsString('ATRASADO', (string) $rows[1][1]);
        self::assertEqualsWithDelta(5146.6620, (float) $rows[1][2], 0.001);
        self::assertEqualsWithDelta(-29.0310, (float) $rows[1][3], 0.001);
        self::assertSame('2026-07-09 00:00:00', $rows[1][5]);
        self::assertStringContainsString('B3 - Boletim Diário do Mercado', (string) $rows[1][6]);
        self::assertSame(174530, (int) $rows[1][7]);
    }

    public function testMarcaAtrasoNaPlanilhaQuandoOBoletimDeHojeAindaNaoFoiPublicado(): void
    {
        $downloadedUrls = [];
        $client = $this->buildClient($downloadedUrls, todaysBulletinExists: false);
        // "Hoje" é 10/07/2026, mas só existe boletim até 09/07/2026 -> atrasado.
        $clock = fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-10');

        $provider = $this->buildProvider($client, $clock);
        $file = $provider->collect();

        $rows = IOFactory::load($file->path)->getActiveSheet()->toArray();

        self::assertStringContainsString('ATRASADO', (string) $rows[1][1]);
        self::assertStringContainsString('Pregão 09/07/2026', (string) $rows[1][1]);
    }

    public function testNaoBaixaOBoletimDeNovoQuandoJaHaCacheParaOMesmoPregao(): void
    {
        $downloadedUrls = [];
        $client = $this->buildClient($downloadedUrls);
        $clock = fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-09');

        $provider = $this->buildProvider($client, $clock);
        $provider->collect();
        $provider->collect();

        self::assertCount(1, $downloadedUrls, 'segunda coleta do mesmo pregão deveria reaproveitar o cache, sem baixar o boletim de novo');
    }

    public function testLancaExcecaoQuandoNenhumContratoElegivelEEncontradoNoBoletim(): void
    {
        $downloadedUrls = [];
        $urlExists = static fn (string $url): bool => true;
        $download = static function (string $url, string $destPath) use (&$downloadedUrls): void {
            $downloadedUrls[] = $url;
            file_put_contents($destPath, 'fake-pdf-bytes');
        };
        // Boletim sem nenhuma linha DOL reconhecível.
        $extractTable = static fn (string $pdfPath): string => 'nenhum dado relevante aqui';

        $client = new B3BulletinClient($urlExists, $download, $extractTable);
        $clock = fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-09');

        $provider = $this->buildProvider($client, $clock);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }

    public function testLancaExcecaoQuandoNenhumBoletimEEncontradoDentroDoLookback(): void
    {
        $urlExists = static fn (string $url): bool => false;
        $download = static function (string $url, string $destPath): void {
        };
        $extractTable = static fn (string $pdfPath): string => '';

        $client = new B3BulletinClient($urlExists, $download, $extractTable);
        $clock = fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-09');

        $provider = $this->buildProvider($client, $clock);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }
}
