<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\B3;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * DolContractCode
 *
 * Código do contrato futuro de dólar da B3 (DOL + letra do mês de
 * vencimento + 2 dígitos do ano) — convenção documentada publicamente pela
 * B3: F=janeiro, G=fevereiro, H=março, J=abril, K=maio, M=junho, N=julho,
 * Q=agosto, U=setembro, V=outubro, X=novembro, Z=dezembro. O vencimento é
 * sempre o primeiro dia útil do mês correspondente.
 *
 * Regra de dia útil implementada aqui: só considera fins de semana (sábado/
 * domingo). Feriados nacionais/da B3 não entram no cálculo — limitação
 * conhecida e deliberada (não existe uma fonte de calendário de feriados
 * integrada ao Collector369 ainda); o efeito prático é que, num mês cujo
 * dia 1º caia em feriado num dia de semana, o vencimento calculado aqui
 * pode ficar 1 dia adiantado em relação ao real. Isso nunca é usado para
 * decidir se o contrato já venceu de forma perigosamente errada, porque a
 * resolução do contrato vigente (DolContractResolver) prioriza o contrato
 * com volume negociado real no boletim, não apenas a data calculada.
 */
final class DolContractCode
{
    private const MONTH_LETTERS = [
        'F' => 1, 'G' => 2, 'H' => 3, 'J' => 4, 'K' => 5, 'M' => 6,
        'N' => 7, 'Q' => 8, 'U' => 9, 'V' => 10, 'X' => 11, 'Z' => 12,
    ];

    private function __construct(
        public readonly string $monthLetter,
        public readonly int $year,
    ) {
    }

    public static function fromParts(string $monthLetter, int $twoDigitYear): self
    {
        if (!array_key_exists($monthLetter, self::MONTH_LETTERS)) {
            throw new InvalidArgumentException("Letra de vencimento inválida: \"{$monthLetter}\".");
        }

        return new self($monthLetter, 2000 + $twoDigitYear);
    }

    /** Ex.: "DOLQ26" */
    public function code(): string
    {
        return 'DOL' . $this->monthLetter . substr((string) $this->year, -2);
    }

    public function month(): int
    {
        return self::MONTH_LETTERS[$this->monthLetter];
    }

    /**
     * Primeiro dia útil (considerando só fins de semana) do mês de
     * vencimento deste contrato.
     */
    public function expiresOn(): DateTimeImmutable
    {
        $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month()));
        $weekday = (int) $firstDay->format('N');

        return match (true) {
            $weekday === 6 => $firstDay->modify('+2 days'),
            $weekday === 7 => $firstDay->modify('+1 day'),
            default => $firstDay,
        };
    }
}
