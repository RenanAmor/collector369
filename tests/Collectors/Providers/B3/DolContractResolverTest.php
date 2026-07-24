<?php

declare(strict_types=1);

namespace Tests\Collectors\Providers\B3;

use Collector369\Collectors\Providers\B3\DolBulletinRow;
use Collector369\Collectors\Providers\B3\DolContractCode;
use Collector369\Collectors\Providers\B3\DolContractResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DolContractResolverTest extends TestCase
{
    private DolContractResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DolContractResolver();
    }

    private function row(string $letter, int $year, ?float $ajuste, ?float $anterior, ?float $variacao, ?int $volume): DolBulletinRow
    {
        return new DolBulletinRow(DolContractCode::fromParts($letter, $year), $ajuste, $anterior, $variacao, $volume);
    }

    public function testEscolheOContratoComMaiorVolumeEntreOsAindaNaoVencidos(): void
    {
        $reference = new DateTimeImmutable('2026-07-09');

        $rows = [
            $this->row('N', 26, 5100.0, 5110.0, -10.0, 500),   // vence 01/07/2026, já venceu
            $this->row('Q', 26, 5146.66, 5175.69, -29.03, 174530), // vigente, alto volume
            $this->row('Z', 26, 5280.48, 5310.60, -30.12, null),   // ilíquido no dia
        ];

        $chosen = $this->resolver->resolve($rows, $reference);

        self::assertNotNull($chosen);
        self::assertSame('DOLQ26', $chosen->contract->code());
    }

    public function testExcluiContratoJaVencidoMesmoComVolumeAlto(): void
    {
        $reference = new DateTimeImmutable('2026-07-09');

        $rows = [
            $this->row('N', 26, 5100.0, 5110.0, -10.0, 999999), // já venceu (01/07/2026)
            $this->row('Q', 26, 5146.66, 5175.69, -29.03, 1),
        ];

        $chosen = $this->resolver->resolve($rows, $reference);

        self::assertSame('DOLQ26', $chosen->contract->code());
    }

    public function testDescartaLinhasComAjusteNaoReconciliadoMesmoComVolume(): void
    {
        $reference = new DateTimeImmutable('2026-07-09');

        $rows = [
            // Variação não bate com Ajuste - Ajuste anterior -> não confiável.
            $this->row('Q', 26, 5146.66, 5175.69, -999.0, 174530),
            $this->row('Z', 26, 5280.48, 5310.60, -30.12, 10),
        ];

        $chosen = $this->resolver->resolve($rows, $reference);

        self::assertSame('DOLZ26', $chosen->contract->code());
    }

    public function testSemVolumeEmNenhumContratoCaiParaOVencimentoMaisProximo(): void
    {
        $reference = new DateTimeImmutable('2026-07-09');

        $rows = [
            $this->row('Z', 26, 5280.48, 5310.60, -30.12, null),
            $this->row('Q', 26, 5146.66, 5175.69, -29.03, null),
        ];

        $chosen = $this->resolver->resolve($rows, $reference);

        self::assertSame('DOLQ26', $chosen->contract->code());
    }

    public function testRetornaNuloQuandoNenhumContratoEElegivel(): void
    {
        $reference = new DateTimeImmutable('2026-07-09');

        $rows = [
            $this->row('N', 26, 5100.0, 5110.0, -10.0, 500), // já vencido
        ];

        self::assertNull($this->resolver->resolve($rows, $reference));
    }
}
