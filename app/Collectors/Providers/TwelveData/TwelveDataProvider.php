<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\TwelveData;

use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Support\Filesystem;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * TwelveDataProvider
 *
 * Provider de cotações via Twelve Data (https://twelvedata.com).
 *
 * Consulta exclusivamente os símbolos informados (validados como
 * suportados durante a Sprint de pesquisa — ver
 * docs/arquitetura/Arquitetura-Multiprovider.md) e padroniza a resposta
 * como uma planilha, para fluir pelo mesmo pipeline de validação e
 * armazenamento já existente. Não trata símbolos não suportados: a
 * lista consultada é definida por quem instancia o Provider.
 */
final class TwelveDataProvider implements CollectorProviderInterface
{
    private const PROVIDER_NAME = 'twelvedata';
    private const API_BASE_URL = 'https://api.twelvedata.com/quote';

    /** Default seguro: limite de créditos por minuto do plano gratuito do Twelve Data. */
    private const DEFAULT_CREDITS_PER_MINUTE = 8;

    private const RATE_LIMIT_COOLDOWN_SECONDS = 61;

    /** Créditos por minuto efetivamente respeitados (também define o tamanho do lote). */
    private readonly int $creditsPerMinute;

    /** @var callable(string): string */
    private $httpGet;

    /** @var callable(): void */
    private $sleep;

    /**
     * @param list<string> $symbols símbolos a consultar
     * @param (callable(string): string)|null $httpGet substituível em testes
     * @param (callable(): void)|null $sleep substituível em testes
     * @param int|null $creditsPerMinute limite de créditos/minuto da API (configurável via
     *  TWELVE_DATA_CREDITS_PER_MINUTE); valores nulos ou não positivos caem no default seguro (8)
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly array $symbols,
        private readonly string $stagingPath,
        ?callable $httpGet = null,
        ?callable $sleep = null,
        ?int $creditsPerMinute = null,
    ) {
        $this->creditsPerMinute = $creditsPerMinute !== null && $creditsPerMinute > 0
            ? $creditsPerMinute
            : self::DEFAULT_CREDITS_PER_MINUTE;

        $this->httpGet = $httpGet ?? self::defaultHttpGet();
        $this->sleep = $sleep ?? static function (): void {
            sleep(self::RATE_LIMIT_COOLDOWN_SECONDS);
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
     * @return array<string, array<string, mixed>>
     */
    private function fetchQuotes(): array
    {
        $quotes = [];

        foreach (array_chunk($this->symbols, $this->creditsPerMinute) as $index => $chunk) {
            if ($index > 0) {
                ($this->sleep)();
            }

            $quotes = [...$quotes, ...$this->fetchChunk($chunk)];
        }

        return $quotes;
    }

    /**
     * @param list<string> $symbols
     * @return array<string, array<string, mixed>>
     */
    private function fetchChunk(array $symbols): array
    {
        $url = self::API_BASE_URL . '?' . http_build_query([
            'symbol' => implode(',', $symbols),
            'apikey' => $this->apiKey,
        ]);

        $response = ($this->httpGet)($url);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new CollectorException('Resposta inválida da API do Twelve Data.');
        }

        if (($decoded['status'] ?? null) === 'error') {
            throw new CollectorException(
                'Erro retornado pela API do Twelve Data: ' . ($decoded['message'] ?? 'erro desconhecido'),
            );
        }

        // Resposta de um único símbolo vem como objeto plano; normaliza para o
        // mesmo formato (símbolo => dados) da resposta de múltiplos símbolos.
        if (isset($decoded['symbol'])) {
            return [$decoded['symbol'] => $decoded];
        }

        return $decoded;
    }

    /**
     * @param array<string, array<string, mixed>> $quotes
     */
    private function writeSpreadsheet(array $quotes): string
    {
        $rows = [['Símbolo', 'Nome', 'Preço', 'Moeda', 'Variação', 'Variação (%)', 'Data/Hora']];

        foreach ($quotes as $symbol => $quote) {
            $rows[] = [
                $quote['symbol'] ?? $symbol,
                $quote['name'] ?? '',
                $quote['close'] ?? '',
                $quote['currency'] ?? '',
                $quote['change'] ?? '',
                $quote['percent_change'] ?? '',
                $quote['datetime'] ?? '',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);

        Filesystem::ensureDirectoryExists($this->stagingPath);

        $path = $this->stagingPath . '/twelvedata_' . date('Y-m-d_His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private static function defaultHttpGet(): callable
    {
        return static function (string $url): string {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($curl);
            $error = curl_error($curl);
            curl_close($curl);

            if ($response === false) {
                throw new CollectorException("Falha ao consultar a API do Twelve Data: {$error}");
            }

            return $response;
        };
    }
}
