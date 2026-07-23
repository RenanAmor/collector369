<?php

declare(strict_types=1);

namespace Tests\Collectors\Providers\YahooFinance;

use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\Providers\YahooFinance\YahooFinanceProvider;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class YahooFinanceProviderTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-yahoofinance');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    private static function chartResponse(array $meta): string
    {
        return json_encode(['chart' => ['result' => [['meta' => $meta]], 'error' => null]]);
    }

    public function testCollectsAndStandardizesAQuoteComputingChangeFromPreviousClose(): void
    {
        $httpGet = function (string $url): string {
            self::assertStringContainsString('query1.finance.yahoo.com/v8/finance/chart/HG%3DF', $url);

            return self::chartResponse([
                'symbol' => 'HG=F',
                'shortName' => 'Copper Sep 26',
                'currency' => 'USD',
                'regularMarketPrice' => 6.3375,
                'chartPreviousClose' => 6.4935,
                'regularMarketTime' => 1784840397,
            ]);
        };

        $provider = new YahooFinanceProvider(['HG=F'], $this->root, $httpGet);
        $file = $provider->collect();

        self::assertSame('yahoofinance', $file->provider);
        self::assertFileExists($file->path);

        $rows = IOFactory::load($file->path)->getActiveSheet()->toArray();

        self::assertSame(
            ['Símbolo', 'Nome', 'Preço', 'Moeda', 'Variação', 'Variação (%)', 'Data/Hora'],
            $rows[0],
        );
        self::assertSame('HG=F', $rows[1][0]);
        self::assertSame('Copper Sep 26', $rows[1][1]);
        self::assertEquals(6.3375, $rows[1][2]);
        self::assertEquals('USD', $rows[1][3]);
        self::assertEqualsWithDelta(-0.156, (float) $rows[1][4], 0.001);
        self::assertEqualsWithDelta(-2.402, (float) $rows[1][5], 0.001);
        self::assertSame(date('Y-m-d H:i:s', 1784840397), $rows[1][6]);
    }

    public function testCollectsMultipleSymbolsSleepingBetweenEachRequest(): void
    {
        $requestedUrls = [];
        $sleptTimes = 0;

        $httpGet = function (string $url) use (&$requestedUrls): string {
            $requestedUrls[] = $url;

            return self::chartResponse(['symbol' => 'X', 'regularMarketPrice' => 1, 'chartPreviousClose' => 1]);
        };

        $sleep = function () use (&$sleptTimes): void {
            $sleptTimes++;
        };

        $provider = new YahooFinanceProvider(['A', 'B', 'C'], $this->root, $httpGet, $sleep);
        $provider->collect();

        self::assertCount(3, $requestedUrls);
        self::assertSame(2, $sleptTimes, 'deve dormir entre requisições, mas não antes da primeira nem depois da última');
    }

    public function testLeavesChangeAndPercentEmptyWhenPreviousCloseIsMissing(): void
    {
        $httpGet = fn (string $url): string => self::chartResponse([
            'symbol' => 'X',
            'regularMarketPrice' => 10.0,
        ]);

        $provider = new YahooFinanceProvider(['X'], $this->root, $httpGet);
        $file = $provider->collect();

        $rows = IOFactory::load($file->path)->getActiveSheet()->toArray();

        self::assertNull($rows[1][4]);
        self::assertNull($rows[1][5]);
    }

    public function testThrowsWhenYahooReturnsAnError(): void
    {
        $httpGet = fn (string $url): string => json_encode([
            'chart' => ['result' => null, 'error' => ['code' => 'Not Found', 'description' => 'No data found, symbol may be delisted']],
        ]);

        $provider = new YahooFinanceProvider(['INVALIDO'], $this->root, $httpGet);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }

    public function testThrowsWhenResponseIsNotValidJson(): void
    {
        $httpGet = fn (string $url): string => 'isso não é json';

        $provider = new YahooFinanceProvider(['X'], $this->root, $httpGet);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }
}
