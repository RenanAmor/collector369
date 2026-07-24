<?php

declare(strict_types=1);

namespace Tests\Collectors\Providers\B3;

use Collector369\Collectors\Providers\B3\DolContractCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DolContractCodeTest extends TestCase
{
    public function testCodeCombinaLetraDoMesEAnoDeDoisDigitos(): void
    {
        $contract = DolContractCode::fromParts('Q', 26);

        self::assertSame('DOLQ26', $contract->code());
        self::assertSame(8, $contract->month());
    }

    public function testVencimentoEPrimeiroDiaUtilQuandoDia1CaiEmDiaDeSemana(): void
    {
        // Agosto/2026: dia 1 é sábado -> primeiro dia útil é segunda 03/08.
        $contract = DolContractCode::fromParts('Q', 26);

        self::assertSame('2026-08-03', $contract->expiresOn()->format('Y-m-d'));
    }

    public function testVencimentoPulaDomingoParaSegunda(): void
    {
        // Novembro/2026: dia 1 é domingo -> primeiro dia útil é 02/11.
        $contract = DolContractCode::fromParts('X', 26);

        self::assertSame('2026-11-02', $contract->expiresOn()->format('Y-m-d'));
    }

    public function testVencimentoPermaneceNoDia1QuandoJaEDiaDeSemana(): void
    {
        // Julho/2026: dia 1 é quarta-feira.
        $contract = DolContractCode::fromParts('N', 26);

        self::assertSame('2026-07-01', $contract->expiresOn()->format('Y-m-d'));
    }

    public function testRejeitaLetraDeMesInvalida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DolContractCode::fromParts('A', 26);
    }
}
