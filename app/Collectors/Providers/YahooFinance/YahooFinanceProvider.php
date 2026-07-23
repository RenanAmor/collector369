<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\YahooFinance;

use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Support\Filesystem;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * YahooFinanceProvider
 *
 * Provider de cotações via o endpoint público de gráfico da Yahoo Finance
 * (`query1.finance.yahoo.com/v8/finance/chart`). Homologado na Sprint 16
 * para cobrir os ativos que a Twelve Data não oferece em nenhum plano
 * (Cobre Futuros, Petróleo WTI Futuros e os 11 Índices da Lista Oficial —
 * ver docs/arquitetura/Sprint-16-Provider-YahooFinance.md).
 *
 * Classificação institucional (Política Oficial de Aquisição de Dados
 * L369, item 2): fonte tipo 3 — automação de interface via chamada HTTP
 * direta a um endpoint não documentado oficialmente pela Yahoo. Não há
 * chave de API, contrato ou versionamento publicado. O endpoint não impõe
 * nenhuma proteção anti-automação (sem CAPTCHA/crumb) — apenas exige um
 * cabeçalho User-Agent de navegador — portanto seu uso não configura
 * contorno de proteção alguma. Ver o documento acima para a justificativa
 * completa de por que nenhuma fonte de nível 1/2 cobre os mesmos ativos.
 *
 * Cada símbolo é consultado individualmente (o endpoint aberto não aceita
 * lote); não trata símbolos não suportados — a lista consultada é definida
 * por quem instancia o Provider.
 */
final class YahooFinanceProvider implements CollectorProviderInterface
{
    private const PROVIDER_NAME = 'yahoofinance';
    private const API_BASE_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';

    /** Intervalo mínimo entre requisições, para não sobrecarregar um endpoint sem limite publicado. */
    private const REQUEST_DELAY_SECONDS = 1;

    /** @var callable(string): string */
    private $httpGet;

    /** @var callable(): void */
    private $sleep;

    /**
     * @param list<string> $symbols símbolos a consultar (formato Yahoo Finance, ex.: 'CL=F', '^HSI')
     * @param (callable(string): string)|null $httpGet substituível em testes
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
        $url = self::API_BASE_URL . rawurlencode($symbol) . '?' . http_build_query([
            'interval' => '1d',
            'range' => '1d',
        ]);

        $response = ($this->httpGet)($url);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new CollectorException("Resposta inválida da Yahoo Finance para {$symbol}.");
        }

        $error = $decoded['chart']['error'] ?? null;

        if ($error !== null) {
            $description = is_array($error) ? ($error['description'] ?? 'erro desconhecido') : (string) $error;

            throw new CollectorException("Erro retornado pela Yahoo Finance para {$symbol}: {$description}");
        }

        $meta = $decoded['chart']['result'][0]['meta'] ?? null;

        if (!is_array($meta)) {
            throw new CollectorException("Resposta da Yahoo Finance sem metadados de cotação para {$symbol}.");
        }

        return $meta;
    }

    /**
     * @param list<array<string, mixed>> $quotes
     */
    private function writeSpreadsheet(array $quotes): string
    {
        $rows = [['Símbolo', 'Nome', 'Preço', 'Moeda', 'Variação', 'Variação (%)', 'Data/Hora']];

        foreach ($quotes as $meta) {
            $price = $meta['regularMarketPrice'] ?? null;
            $previousClose = $meta['chartPreviousClose'] ?? null;
            $change = $this->computeChange($price, $previousClose);
            $changePercent = $this->computeChangePercent($change, $previousClose);

            $rows[] = [
                $meta['symbol'] ?? '',
                $meta['shortName'] ?? '',
                $price ?? '',
                $meta['currency'] ?? '',
                $change ?? '',
                $changePercent ?? '',
                $this->formatDateTime($meta['regularMarketTime'] ?? null),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);

        Filesystem::ensureDirectoryExists($this->stagingPath);

        $path = $this->stagingPath . '/yahoofinance_' . date('Y-m-d_His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function computeChange(mixed $price, mixed $previousClose): ?float
    {
        if (!is_numeric($price) || !is_numeric($previousClose)) {
            return null;
        }

        return (float) $price - (float) $previousClose;
    }

    private function computeChangePercent(?float $change, mixed $previousClose): ?float
    {
        if ($change === null || !is_numeric($previousClose) || (float) $previousClose === 0.0) {
            return null;
        }

        return ($change / (float) $previousClose) * 100;
    }

    private function formatDateTime(mixed $unixTimestamp): string
    {
        if (!is_numeric($unixTimestamp)) {
            return '';
        }

        return date('Y-m-d H:i:s', (int) $unixTimestamp);
    }

    private static function defaultHttpGet(): callable
    {
        return static function (string $url): string {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0 (Collector369)']);

            $response = curl_exec($curl);
            $error = curl_error($curl);
            curl_close($curl);

            if ($response === false) {
                throw new CollectorException("Falha ao consultar a Yahoo Finance: {$error}");
            }

            return $response;
        };
    }
}
