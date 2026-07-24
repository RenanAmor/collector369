<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\B3;

/**
 * B3BulletinTableParser
 *
 * Extrai as linhas do contrato futuro de dólar (DOL puro, sem sufixo de
 * opção como "C00700"/"P00700") da tabela "Negócios consolidados do
 * pregão" do Boletim Diário do Mercado da B3 (arquivo BDI_00, seção
 * Derivativos), a partir do texto gerado por `pdftotext -table`.
 *
 * Por que `-table` e não `-layout`: o layout dessa tabela específica tem
 * células que quebram em múltiplas linhas visuais (ex.: o "Segmento" de
 * alguns instrumentos), o que faz o modo `-layout` do pdftotext
 * ocasionalmente deslocar valores para a coluna errada em linhas
 * adjacentes — confirmado comparando os dois modos contra os próprios
 * dados da B3 (a coluna "Variação" bate exatamente com Ajuste menos
 * Ajuste do dia anterior só no modo `-table`; no `-layout` os números
 * batidos aqui vinham de outra linha). `-table` não teve esse problema em
 * nenhuma linha auditada manualmente.
 *
 * Verificação extra, por linha (não só confiar no parsing posicional): a
 * B3 publica "Variação" = Ajuste - Ajuste do dia anterior no próprio
 * boletim. `DolBulletinRow::hasReconciledAjuste()` reprocessa essa conta e
 * só uma linha que bate exatamente (tolerância de 1 centavo) é aceita como
 * fonte confiável de preço — qualquer divergência é tratada como possível
 * erro de extração e a linha é descartada, nunca usada.
 */
final class B3BulletinTableParser
{
    private const ROW_PATTERN = '/^DOL([FGHJKMNQUVXZ])(\d{2})\s+\S+\s+\S+\s+(.+)$/';

    /** Índices (0-based) dentro dos 17 campos numéricos da linha. */
    private const COLUMN_COUNT = 17;
    private const COLUMN_AJUSTE = 6;
    private const COLUMN_AJUSTE_DIA_ANTERIOR = 8;
    private const COLUMN_VARIACAO = 10;
    private const COLUMN_QUANTIDADE_CONTRATOS = 15;

    /**
     * @return list<DolBulletinRow>
     */
    public function parse(string $tableText): array
    {
        $rows = [];

        foreach (explode("\n", $tableText) as $line) {
            $row = $this->parseLine(rtrim($line, "\r"));

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function parseLine(string $line): ?DolBulletinRow
    {
        if (preg_match(self::ROW_PATTERN, $line, $matches) !== 1) {
            return null;
        }

        $fields = preg_split('/\s+/', trim($matches[3]));

        if ($fields === false || count($fields) !== self::COLUMN_COUNT) {
            return null;
        }

        $contract = DolContractCode::fromParts($matches[1], (int) $matches[2]);

        return new DolBulletinRow(
            contract: $contract,
            ajuste: $this->toFloat($fields[self::COLUMN_AJUSTE]),
            ajusteDiaAnterior: $this->toFloat($fields[self::COLUMN_AJUSTE_DIA_ANTERIOR]),
            variacao: $this->toFloat($fields[self::COLUMN_VARIACAO]),
            quantidadeContratos: $this->toInt($fields[self::COLUMN_QUANTIDADE_CONTRATOS]),
        );
    }

    private function toFloat(string $token): ?float
    {
        if ($token === '-' || $token === '') {
            return null;
        }

        $normalized = str_replace(['.', ','], ['', '.'], $token);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function toInt(string $token): ?int
    {
        $value = $this->toFloat($token);

        return $value !== null ? (int) round($value) : null;
    }
}
