<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\B3;

use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Support\Filesystem;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * B3DolfutProvider
 *
 * Coleta o preço de ajuste (fechamento oficial) do contrato futuro de
 * dólar vigente da B3 (DOLFUT) a partir do Boletim Diário do Mercado
 * público — ver B3BulletinClient (download/localização do boletim),
 * B3BulletinTableParser (extração e reconciliação aritmética das linhas)
 * e DolContractResolver (qual contrato é o vigente).
 *
 * Não classifica nem pontua (sobe/cai/neutro, voto Risco x Segurança é
 * responsabilidade do Motor Macro/Laboratório 369, fora do escopo do
 * Collector369) — entrega preço de ajuste atual, ajuste do dia anterior e
 * metadados do contrato, no mesmo formato de planilha padronizado
 * (Símbolo/Preço/Variação/Variação (%)/Data-Hora) que os demais Providers,
 * para fluir pelo mesmo pipeline de ingestão do Motor Macro sem exigir
 * nenhuma alteração ali.
 *
 * Cache por pregão (storage/cache/b3dolfut/{data}.json): o ajuste é um
 * dado de fechamento diário, não intraday — sem cache, cada ciclo de 5
 * minutos baixaria de novo o boletim inteiro (~40MB) só para ler o mesmo
 * valor do dia. A resolução de qual data usar (resolveLatestBulletinDate)
 * continua rodando a cada coleta (só requisições HEAD, baratas), então o
 * flag de atraso reflete sempre o momento real da coleta mesmo quando o
 * preço vem do cache.
 */
final class B3DolfutProvider implements CollectorProviderInterface
{
    private const PROVIDER_NAME = 'b3dolfut';
    private const SOURCE_LABEL = 'B3 - Boletim Diário do Mercado';
    private const SYMBOL = 'DOLFUT';

    /** @var callable(): DateTimeImmutable */
    private $clock;

    /**
     * @param (callable(): DateTimeImmutable)|null $clock substituível em testes
     */
    public function __construct(
        private readonly B3BulletinClient $client,
        private readonly B3BulletinTableParser $parser,
        private readonly DolContractResolver $resolver,
        private readonly string $cachePath,
        private readonly string $stagingPath,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    public function collect(): CollectedFile
    {
        $now = ($this->clock)();
        $resolved = $this->client->resolveLatestBulletinDate($now);
        $pregaoDate = $resolved['date'];

        $priced = $this->loadFromCache($pregaoDate) ?? $this->fetchAndCache($resolved['url'], $pregaoDate);

        $path = $this->writeSpreadsheet($priced, $pregaoDate, $resolved['delayed'], $resolved['url']);

        return new CollectedFile(
            path: $path,
            provider: self::PROVIDER_NAME,
            collectedAt: $now,
            originalFilename: basename($path),
        );
    }

    /**
     * @return array{contract: string, expires_on: string, ajuste: float, ajuste_dia_anterior: float, quantidade_contratos: int|null}|null
     */
    private function loadFromCache(DateTimeImmutable $pregaoDate): ?array
    {
        $cacheFile = $this->cacheFilePath($pregaoDate);

        if (!file_exists($cacheFile)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($cacheFile), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{contract: string, expires_on: string, ajuste: float, ajuste_dia_anterior: float, quantidade_contratos: int|null}
     */
    private function fetchAndCache(string $url, DateTimeImmutable $pregaoDate): array
    {
        $tableText = $this->client->downloadAndExtractTable($url);
        $rows = $this->parser->parse($tableText);
        $chosen = $this->resolver->resolve($rows, $pregaoDate);

        if ($chosen === null || $chosen->ajuste === null || $chosen->ajusteDiaAnterior === null) {
            throw new CollectorException(
                "Nenhum contrato DOLFUT elegível (ainda não vencido, com ajuste reconciliado) encontrado no boletim B3 de {$pregaoDate->format('Y-m-d')}."
            );
        }

        $data = [
            'contract' => $chosen->contract->code(),
            'expires_on' => $chosen->contract->expiresOn()->format('Y-m-d'),
            'ajuste' => $chosen->ajuste,
            'ajuste_dia_anterior' => $chosen->ajusteDiaAnterior,
            'quantidade_contratos' => $chosen->quantidadeContratos,
        ];

        Filesystem::ensureDirectoryExists($this->cachePath);
        file_put_contents($this->cacheFilePath($pregaoDate), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $data;
    }

    private function cacheFilePath(DateTimeImmutable $pregaoDate): string
    {
        return $this->cachePath . '/' . $pregaoDate->format('Y-m-d') . '.json';
    }

    /**
     * @param array{contract: string, expires_on: string, ajuste: float, ajuste_dia_anterior: float, quantidade_contratos: int|null} $priced
     */
    private function writeSpreadsheet(array $priced, DateTimeImmutable $pregaoDate, bool $delayed, string $sourceUrl): string
    {
        $variacao = $priced['ajuste'] - $priced['ajuste_dia_anterior'];
        $variacaoPct = $priced['ajuste_dia_anterior'] !== 0.0 ? ($variacao / $priced['ajuste_dia_anterior']) * 100 : null;

        $expiresOn = new DateTimeImmutable($priced['expires_on']);
        $detail = sprintf(
            'Contrato %s · Venc. %s · Pregão %s',
            $priced['contract'],
            $expiresOn->format('d/m/Y'),
            $pregaoDate->format('d/m/Y'),
        );

        if ($delayed) {
            $detail .= ' · ATRASADO (boletim mais recente disponível na B3)';
        }

        $rows = [
            ['Símbolo', 'Contrato/Vencimento', 'Preço', 'Variação', 'Variação (%)', 'Data/Hora', 'Fonte', 'Volume'],
            [
                self::SYMBOL,
                $detail,
                $priced['ajuste'],
                $variacao,
                $variacaoPct ?? '',
                $pregaoDate->format('Y-m-d') . ' 00:00:00',
                self::SOURCE_LABEL . ' (' . $sourceUrl . ')',
                $priced['quantidade_contratos'] ?? '',
            ],
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows);

        // Worksheet::fromArray() trata 0/0.0 como célula vazia (checagem de
        // falsy interna do PhpSpreadsheet) — um dia em que o ajuste não muda
        // ("valores iguais = neutro", regra explícita da Sprint 20) faria a
        // Variação sumir da planilha e o item inteiro ser descartado na
        // ingestão (variation_pct === null). Regrava explicitamente como
        // número as colunas que podem legitimamente valer exatamente zero.
        $numericCells = [
            'C2' => $priced['ajuste'],
            'D2' => $variacao,
            'E2' => $variacaoPct,
            'H2' => $priced['quantidade_contratos'],
        ];

        foreach ($numericCells as $cell => $value) {
            if ($value !== null) {
                $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
            }
        }

        Filesystem::ensureDirectoryExists($this->stagingPath);

        $path = $this->stagingPath . '/b3dolfut_' . date('Y-m-d_His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
