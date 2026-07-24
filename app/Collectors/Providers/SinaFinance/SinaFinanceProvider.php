<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\SinaFinance;

use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Support\Filesystem;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * SinaFinanceProvider
 *
 * Provider de cotações via o feed de tempo real da Sina Finance
 * (`hq.sinajs.cn/list=nf_<símbolo>`) — o mesmo endpoint consumido pelo
 * próprio widget de cotação de `finance.sina.com.cn/futures/quotes/`,
 * usado aqui diretamente em vez de depender da renderização visual da
 * página (decisão do PO, Sprint 16). Homologado para o Minério de Ferro
 * (contrato contínuo `I0`, Dalian Commodity Exchange), que não pode ser
 * substituído por ETF/proxy.
 *
 * Classificação institucional (Política Oficial de Aquisição de Dados
 * L369, item 2): fonte tipo 3 — automação de interface via chamada HTTP
 * direta a um endpoint não documentado oficialmente pela Sina. O endpoint
 * exige um cabeçalho Referer correspondente ao próprio domínio de origem
 * (sem o qual retorna 403) — o mesmo valor estático que a página legítima
 * já envia ao carregar seu próprio widget de cotação; não há CAPTCHA,
 * crumb ou desafio de sessão. Ver
 * docs/arquitetura/Sprint-16-Provider-SinaFinance.md para a justificativa
 * completa e o mapeamento de campos (verificado contra a implementação de
 * referência do projeto open source AKShare, que consome o mesmo feed).
 *
 * O feed retorna uma string separada por vírgulas, codificada em GBK, no
 * formato `var hq_str_nf_<símbolo>="campo0,campo1,...";`. Os índices
 * relevantes (nome, hora, preço atual, preço de liquidação anterior, data)
 * são fixos e documentados em `parseQuote()`.
 */
final class SinaFinanceProvider implements CollectorProviderInterface
{
    private const PROVIDER_NAME = 'sinafinance';
    private const API_BASE_URL = 'https://hq.sinajs.cn/list=nf_';
    private const SOURCE_LABEL = 'Sina Finance (hq.sinajs.cn)';

    /** Intervalo mínimo entre requisições, para não sobrecarregar um endpoint sem limite publicado. */
    private const REQUEST_DELAY_SECONDS = 1;

    /** @var callable(string): string */
    private $httpGet;

    /** @var callable(): void */
    private $sleep;

    /**
     * @param list<string> $symbols símbolos Sina (ex.: 'I0' para o contínuo de Minério de Ferro na DCE)
     * @param (callable(string): string)|null $httpGet substituível em testes; deve retornar a resposta já em GBK (bytes crus)
     * @param (callable(): void)|null $sleep substituível em testes
     */
    public function __construct(
        private readonly array $symbols,
        private readonly string $stagingPath,
        ?callable $httpGet = null,
        ?callable $sleep = null,
    ) {
        $this->httpGet = $httpGet ?? self::defaultHttpGet();
        $this->sleep = $sleep ?? static function (): void {
            sleep(self::REQUEST_DELAY_SECONDS);
        };
    }

    public function collect(): CollectedFile
    {
        $quotes = $this->fetchQuotes();
        $path = $this->writeSpreadsheet($quotes);

        return new CollectedFile(
            path: $path,
            provider: self::PROVIDER_NAME,
            collectedAt: new DateTimeImmutable(),
            originalFilename: basename($path),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchQuotes(): array
    {
        $quotes = [];

        foreach ($this->symbols as $index => $symbol) {
            if ($index > 0) {
                ($this->sleep)();
            }

            $quotes[] = $this->fetchOne($symbol);
        }

        return $quotes;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchOne(string $symbol): array
    {
        $url = self::API_BASE_URL . rawurlencode($symbol);
        $response = ($this->httpGet)($url);
        $decoded = mb_convert_encoding($response, 'UTF-8', 'GBK');

        return $this->parseQuote($symbol, $decoded);
    }

    /**
     * Extrai os campos do formato `var hq_str_nf_<símbolo>="c0,c1,...";`.
     *
     * Mapeamento de índices verificado contra a implementação de
     * referência do AKShare (`futures_zh_sina.py::futures_zh_spot`, market
     * "CF"): 0=nome, 1=hora (HHMMSS), 2=abertura, 3=máxima, 4=mínima,
     * 5=fechamento anterior, 6=preço de compra, 7=preço de venda,
     * 8=preço atual, 9=preço médio, 10=preço de liquidação anterior,
     * 11=volume de compra, 12=volume de venda, 13=posições em aberto,
     * 14=volume. Os campos de data (índice 17, formato YYYY-MM-DD) foram
     * confirmados por inspeção direta de uma resposta real do feed — não
     * fazem parte do mapeamento documentado pelo AKShare (que os descarta
     * como colunas extras "_"), mas são estáveis e sempre presentes nessa
     * posição na resposta observada.
     *
     * @return array<string, mixed>
     */
    private function parseQuote(string $symbol, string $decoded): array
    {
        if (!preg_match('/="(.*)";?\s*$/', trim($decoded), $matches)) {
            throw new CollectorException("Resposta inválida da Sina Finance para {$symbol}: {$decoded}");
        }

        $fields = explode(',', $matches[1]);

        if (count($fields) < 18 || $fields[0] === '') {
            throw new CollectorException("Resposta da Sina Finance sem dados de cotação para {$symbol}.");
        }

        return [
            'symbol' => $symbol,
            'name' => $fields[0],
            'currentPrice' => $fields[8],
            'lastSettlePrice' => $fields[10],
            'time' => $fields[1],
            'date' => $fields[17],
            'volume' => $fields[14],
        ];
    }

    /**
     * @param list<array<string, mixed>> $quotes
     */
    private function writeSpreadsheet(array $quotes): string
    {
        $rows = [['Símbolo', 'Nome', 'Preço', 'Moeda', 'Variação', 'Variação (%)', 'Data/Hora', 'Fonte', 'Volume']];

        foreach ($quotes as $quote) {
            $price = is_numeric($quote['currentPrice']) ? (float) $quote['currentPrice'] : null;
            $lastSettle = is_numeric($quote['lastSettlePrice']) ? (float) $quote['lastSettlePrice'] : null;
            $change = $this->computeChange($price, $lastSettle);
            $changePercent = $this->computeChangePercent($change, $lastSettle);

            $rows[] = [
                $quote['symbol'],
                $quote['name'],
                $price ?? '',
                'CNY',
                $change ?? '',
                $changePercent ?? '',
                $this->formatDateTime($quote['date'], $quote['time']),
                self::SOURCE_LABEL,
                $quote['volume'] ?? '',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);

        Filesystem::ensureDirectoryExists($this->stagingPath);

        $path = $this->stagingPath . '/sinafinance_' . date('Y-m-d_His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function computeChange(?float $price, ?float $lastSettle): ?float
    {
        if ($price === null || $lastSettle === null) {
            return null;
        }

        return $price - $lastSettle;
    }

    private function computeChangePercent(?float $change, ?float $lastSettle): ?float
    {
        if ($change === null || $lastSettle === null || $lastSettle === 0.0) {
            return null;
        }

        return ($change / $lastSettle) * 100;
    }

    private function formatDateTime(string $date, string $time): string
    {
        if ($date === '' || $time === '' || strlen($time) !== 6) {
            return $date;
        }

        return sprintf(
            '%s %s:%s:%s',
            $date,
            substr($time, 0, 2),
            substr($time, 2, 2),
            substr($time, 4, 2),
        );
    }

    private static function defaultHttpGet(): callable
    {
        return static function (string $url): string {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (Collector369)',
                'Referer: https://finance.sina.com.cn/',
            ]);

            $response = curl_exec($curl);
            $error = curl_error($curl);
            curl_close($curl);

            if ($response === false) {
                throw new CollectorException("Falha ao consultar a Sina Finance: {$error}");
            }

            return $response;
        };
    }
}
