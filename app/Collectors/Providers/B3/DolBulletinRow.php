<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\B3;

/**
 * DolBulletinRow
 *
 * Uma linha do contrato futuro de dólar (DOL, sem sufixo de opção) extraída
 * da tabela "Negócios consolidados do pregão" do Boletim Diário do Mercado
 * da B3. Todos os campos de preço são nulos quando o boletim não publica
 * valor para aquele contrato naquele pregão (nunca substituído por zero).
 */
final class DolBulletinRow
{
    public function __construct(
        public readonly DolContractCode $contract,
        public readonly ?float $ajuste,
        public readonly ?float $ajusteDiaAnterior,
        public readonly ?float $variacao,
        public readonly ?int $quantidadeContratos,
    ) {
    }

    public function hasReconciledAjuste(): bool
    {
        if ($this->ajuste === null || $this->ajusteDiaAnterior === null || $this->variacao === null) {
            return false;
        }

        return abs(($this->ajuste - $this->ajusteDiaAnterior) - $this->variacao) < 0.01;
    }
}
