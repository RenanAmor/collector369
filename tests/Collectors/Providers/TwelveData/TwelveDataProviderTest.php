<?php

declare(strict_types=1);

namespace Tests\Collectors\Providers\TwelveData;

use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\Providers\TwelveData\TwelveDataProvider;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class TwelveDataProviderTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-twelvedata');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testCollectsAndStandardizesASingleSymbolResponse(): void
    {
        $httpGet = function (string $url): string {
            self::assertStringContainsString('symbol=AAA', $url);
            self::assertStringContainsString('apikey=fake-key', $url);

            return json_encode([
                'symbol' => 'AAA',
                'name' => 'Ativo A',
                'currency' => 'USD',
                'close' => '10.50',
                'change' => '0.25',
                'percent_change' => '2.44',
                'datetime' => '2026-07-22',
            ]);
        };

        $provider = new TwelveDataProvider('fake-key', ['AAA'], $this->root, $httpGet);
        $file = $provider->collect();

        self::assertSame('twelvedata', $file->provider);
        self::assertFileExists($file->path);

        $rows = IOFactory::load($file->path)->getActiveSheet()->toArray();

        self::assertSame(
            ['Símbolo', 'Nome', 'Preço', 'Moeda', 'Variação', 'Variação (%)', 'Data/Hora', 'Volume'],
            $rows[0],
        );
        self::assertSame('AAA', $rows[1][0]);
        self::assertSame('Ativo A', $rows[1][1]);
        self::assertEquals(10.50, $rows[1][2]);
    }

    public function testCollectsAndStandardizesAMultiSymbolResponse(): void
    {
        $httpGet = fn (string $url): string => json_encode([
            'AAA' => ['symbol' => 'AAA', 'name' => 'Ativo A', 'currency' => 'USD', 'close' => '10.50', 'change' => '0.1', 'percent_change' => '1.0', 'datetime' => '2026-07-22'],
            'BBB' => ['symbol' => 'BBB', 'name' => 'Ativo B', 'currency' => 'USD', 'close' => '20.00', 'change' => '-0.2', 'percent_change' => '-0.9', 'datetime' => '2026-07-22'],
        ]);

        $provider = new TwelveDataProvider('fake-key', ['AAA', 'BBB'], $this->root, $httpGet);
        $file = $provider->collect();

        $rows = IOFactory::load($file->path)->getActiveSheet()->toArray();

        self::assertCount(3, $rows);
        self::assertSame('AAA', $rows[1][0]);
        self::assertSame('BBB', $rows[2][0]);
    }

    public function testSplitsRequestsIntoChunksRespectingTheRateLimit(): void
    {
        $requestedUrls = [];
        $sleptTimes = 0;

        $httpGet = function (string $url) use (&$requestedUrls): string {
            $requestedUrls[] = $url;

            return json_encode(['AAA' => ['symbol' => 'AAA', 'close' => '1']]);
        };

        $sleep = function () use (&$sleptTimes): void {
            $sleptTimes++;
        };

        $symbols = ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9', 'A10'];

        $provider = new TwelveDataProvider('fake-key', $symbols, $this->root, $httpGet, $sleep);
        $provider->collect();

        self::assertCount(2, $requestedUrls, '10 símbolos em lotes de 8 devem gerar 2 chamadas');
        self::assertSame(1, $sleptTimes, 'deve dormir entre lotes, mas não antes do primeiro nem depois do último');
    }

    public function testRespectsAConfigurableCreditsPerMinuteLimit(): void
    {
        $requestedUrls = [];
        $sleptTimes = 0;

        $httpGet = function (string $url) use (&$requestedUrls): string {
            $requestedUrls[] = $url;

            return json_encode(['AAA' => ['symbol' => 'AAA', 'close' => '1']]);
        };

        $sleep = function () use (&$sleptTimes): void {
            $sleptTimes++;
        };

        $symbols = ['A1', 'A2', 'A3', 'A4', 'A5'];

        $provider = new TwelveDataProvider('fake-key', $symbols, $this->root, $httpGet, $sleep, creditsPerMinute: 2);
        $provider->collect();

        self::assertCount(3, $requestedUrls, '5 símbolos em lotes de 2 devem gerar 3 chamadas');
        self::assertSame(2, $sleptTimes, 'deve dormir entre lotes, mas não antes do primeiro nem depois do último');
    }

    public function testFallsBackToTheSafeDefaultWhenCreditsPerMinuteIsNotPositive(): void
    {
        $requestedUrls = [];

        $httpGet = function (string $url) use (&$requestedUrls): string {
            $requestedUrls[] = $url;

            return json_encode(['AAA' => ['symbol' => 'AAA', 'close' => '1']]);
        };

        $symbols = ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9'];

        $provider = new TwelveDataProvider('fake-key', $symbols, $this->root, $httpGet, creditsPerMinute: 0);
        $provider->collect();

        self::assertCount(2, $requestedUrls, '9 símbolos com limite inválido devem cair no default seguro de 8 (lotes de 8 e 1)');
    }

    public function testDoesNotSleepWhenAllSymbolsFitInASingleChunk(): void
    {
        $sleptTimes = 0;

        $httpGet = fn (string $url): string => json_encode(['AAA' => ['symbol' => 'AAA', 'close' => '1']]);

        $sleep = function () use (&$sleptTimes): void {
            $sleptTimes++;
        };

        $provider = new TwelveDataProvider('fake-key', ['A1', 'A2'], $this->root, $httpGet, $sleep, creditsPerMinute: 8);
        $provider->collect();

        self::assertSame(0, $sleptTimes, 'não deve esperar quando todos os símbolos cabem num único lote');
    }

    public function testThrowsWhenApiReturnsErrorStatus(): void
    {
        $httpGet = fn (string $url): string => json_encode([
            'code' => 429,
            'message' => 'rate limit',
            'status' => 'error',
        ]);

        $provider = new TwelveDataProvider('fake-key', ['AAA'], $this->root, $httpGet);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }

    public function testThrowsWhenResponseIsNotValidJson(): void
    {
        $httpGet = fn (string $url): string => 'isso não é json';

        $provider = new TwelveDataProvider('fake-key', ['AAA'], $this->root, $httpGet);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }
}
