<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\B3;

use DateTimeImmutable;

/**
 * DolContractResolver
 *
 * Resolve qual linha do boletim representa o "contrato vigente" de DOLFUT
 * — regra verificável, não hardcoded: entre os contratos ainda não
 * vencidos (vencimento >= data de referência) e com ajuste reconciliado
 * (ver DolBulletinRow::hasReconciledAjuste), escolhe o de maior volume
 * negociado no pregão (quantidade de contratos). Esse é o contrato
 * realmente líquido do dia, não uma suposição de calendário. Em caso de
 * nenhum contrato ter volume (> 0) — ex.: pregão atípico —, cai para o
 * contrato de vencimento mais próximo entre os elegíveis.
 */
final class DolContractResolver
{
    /**
     * @param list<DolBulletinRow> $rows
     */
    public function resolve(array $rows, DateTimeImmutable $referenceDate): ?DolBulletinRow
    {
        $eligible = array_values(array_filter(
            $rows,
            static fn (DolBulletinRow $row): bool => $row->hasReconciledAjuste()
                && $row->contract->expiresOn() >= $referenceDate,
        ));

        if ($eligible === []) {
            return null;
        }

        usort(
            $eligible,
            static function (DolBulletinRow $a, DolBulletinRow $b): int {
                $volumeCompare = ($b->quantidadeContratos ?? 0) <=> ($a->quantidadeContratos ?? 0);

                if ($volumeCompare !== 0) {
                    return $volumeCompare;
                }

                return $a->contract->expiresOn() <=> $b->contract->expiresOn();
            },
        );

        return $eligible[0];
    }
}
