<?php

declare(strict_types=1);

namespace Tests\Collectors\Providers\SinaFinance;

use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\Providers\SinaFinance\SinaFinanceProvider;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class SinaFinanceProviderTest extends TestCase
{
    use InteractsWithTempDirectories;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDirectory('collector369-sinafinance');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    /**
     * Monta uma resposta crua do feed da Sina, já em GBK, no mesmo formato
     * real observado em `hq.sinajs.cn/list=nf_I0` — nome em chinês seguido
     * dos campos posicionais (ver mapeamento em SinaFinanceProvider::parseQuote).
     */
    private static function rawGbkResponse(string $symbol, string $name): string
    {
        $fields = [
            $name, '230000', '747.500', '749.500', '742.500', '0.000',
            '743.500', '744.000', '743.500', '0.000', '747.000', '9', '308',
            '487626.000', '57834', '连', $name, '2026-07-23', '1',
        ];

        $utf8 = 'var hq_str_nf_' . $symbol . '="' . implode(',', $fields) . '";' . "\n";

        return mb_convert_encoding($utf8, 'GBK', 'UTF-8');
    }

    public function testCollectsAndStandardizesAQuoteComputingChangeFromLastSettlePrice(): void
    {
        $httpGet = function (string $url): string {
            self::assertStringContainsString('hq.sinajs.cn/list=nf_I0', $url);

            return self::rawGbkResponse('I0', '铁矿石连续');
        };

        $provider = new SinaFinanceProvider(['I0'], $this->root, $httpGet);
        $file = $provider->collect();

        self::assertSame('sinafinance', $file->provider);
        self::assertFileExists($file->path);

        $rows = IOFactory::load($file->path)->getActiveSheet()->toArray();

        self::assertSame(
            ['Símbolo', 'Nome', 'Preço', 'Moeda', 'Variação', 'Variação (%)', 'Data/Hora', 'Fonte', 'Volume'],
            $rows[0],
        );
        self::assertSame('I0', $rows[1][0]);
        self::assertSame('铁矿石连续', $rows[1][1]);
        self::assertEqualsWithDelta(743.5, (float) $rows[1][2], 0.001);
        self::assertSame('CNY', $rows[1][3]);
        self::assertEqualsWithDelta(-3.5, (float) $rows[1][4], 0.001);
        self::assertEqualsWithDelta(-0.4685, (float) $rows[1][5], 0.001);
        self::assertSame('2026-07-23 23:00:00', $rows[1][6]);
        self::assertSame('Sina Finance (hq.sinajs.cn)', $rows[1][7]);
    }

    public function testCollectsMultipleSymbolsSleepingBetweenEachRequest(): void
    {
        $requestedUrls = [];
        $sleptTimes = 0;

        $httpGet = function (string $url) use (&$requestedUrls): string {
            $requestedUrls[] = $url;

            return self::rawGbkResponse('X', 'Ativo X');
        };

        $sleep = function () use (&$sleptTimes): void {
            $sleptTimes++;
        };

        $provider = new SinaFinanceProvider(['A', 'B'], $this->root, $httpGet, $sleep);
        $provider->collect();

        self::assertCount(2, $requestedUrls);
        self::assertSame(1, $sleptTimes);
    }

    public function testThrowsWhenResponseHasNoQuotedData(): void
    {
        $httpGet = fn (string $url): string => mb_convert_encoding('var hq_str_nf_I0="";' . "\n", 'GBK', 'UTF-8');

        $provider = new SinaFinanceProvider(['I0'], $this->root, $httpGet);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }

    public function testThrowsWhenResponseIsForbidden(): void
    {
        $httpGet = fn (string $url): string => 'Forbidden';

        $provider = new SinaFinanceProvider(['I0'], $this->root, $httpGet);

        $this->expectException(CollectorException::class);

        $provider->collect();
    }
}
