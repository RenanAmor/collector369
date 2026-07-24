<?php

declare(strict_types=1);

namespace Tests\Collectors\Providers\B3;

use Collector369\Collectors\Providers\B3\B3BulletinTableParser;
use PHPUnit\Framework\TestCase;

final class B3BulletinTableParserTest extends TestCase
{
    private B3BulletinTableParser $parser;

    protected function setUp(): void
    {
        $this->parser = new B3BulletinTableParser();
    }

    /**
     * Linha real do Boletim Diário do Mercado da B3 (BDI_00, 09/07/2026,
     * seção Derivativos > Negócios consolidados do pregão), extraída via
     * `pdftotext -table` e auditada manualmente contra os campos OHLC da
     * própria linha: Ajuste (5.146,6620) - Ajuste do dia anterior
     * (5.175,6930) = Variação (-29,0310), e Variação × 50 (tamanho do
     * contrato DOL) = Valor do ajuste por contrato (-1.451,55).
     */
    private const REAL_DOLQ26_LINE =
        'DOLQ26       BRBMEFDOL835  FINANCIAL  5.178,0000   5.137,0000  5.184,5000  5.154,7380  5.142,5000      -0,64     5.146,6620              -  5.175,6930                -  -29,0310  -1.451,55   5.139,0000    5.145,5000    12.805       174.530       44.982.823,12';

    /** Linha de um contrato sem negócios no dia (ilíquido), mas com ajuste teórico publicado. */
    private const REAL_DOLZ26_LINE =
        'DOLZ26       BRBMEFDOL850  FINANCIAL            -          -           -          -              -            -  5.280,4810              -  5.310,5980                -  -30,1170  -1.505,85              -             -            -             -              -';

    public function testExtraiAjusteEAjusteAnteriorDaLinhaRealComVolume(): void
    {
        $rows = $this->parser->parse(self::REAL_DOLQ26_LINE);

        self::assertCount(1, $rows);
        $row = $rows[0];

        self::assertSame('DOLQ26', $row->contract->code());
        self::assertEqualsWithDelta(5146.6620, $row->ajuste, 0.0001);
        self::assertEqualsWithDelta(5175.6930, $row->ajusteDiaAnterior, 0.0001);
        self::assertEqualsWithDelta(-29.0310, $row->variacao, 0.0001);
        self::assertSame(174530, $row->quantidadeContratos);
        self::assertTrue($row->hasReconciledAjuste());
    }

    public function testContratoSemNegocioNoDiaAindaTemAjusteTeoricoEQuantidadeNula(): void
    {
        $rows = $this->parser->parse(self::REAL_DOLZ26_LINE);

        self::assertCount(1, $rows);
        $row = $rows[0];

        self::assertSame('DOLZ26', $row->contract->code());
        self::assertEqualsWithDelta(5280.4810, $row->ajuste, 0.0001);
        self::assertNull($row->quantidadeContratos);
        self::assertTrue($row->hasReconciledAjuste());
    }

    public function testIgnoraLinhasDeOpcoesSobreDolFut(): void
    {
        $rows = $this->parser->parse(
            'DOLQ26C00592   BRBMEFDA0593  FINANCIAL            -          -          -         -            -            -                -            -                -  0,0040                -           -             -             -            -             -              -'
        );

        self::assertSame([], $rows);
    }

    public function testDescartaLinhaCujaAritmeticaNaoReconciliaComAVariacaoPublicada(): void
    {
        // Ajuste - Ajuste anterior deveria ser -29,0310, não bate com o valor
        // de Variação informado (-999,0000) -- indício de extração corrompida,
        // a linha não pode ser usada como fonte confiável.
        $rows = $this->parser->parse(
            'DOLQ26       BRBMEFDOL835  FINANCIAL  5.178,0000   5.137,0000  5.184,5000  5.154,7380  5.142,5000      -0,64     5.146,6620              -  5.175,6930                -  -999,0000  -1.451,55   5.139,0000    5.145,5000    12.805       174.530       44.982.823,12'
        );

        self::assertCount(1, $rows);
        self::assertFalse($rows[0]->hasReconciledAjuste());
    }

    public function testIgnoraLinhaComNumeroDeColunasInesperado(): void
    {
        $rows = $this->parser->parse('DOLQ26       BRBMEFDOL835  FINANCIAL  5.178,0000   5.137,0000');

        self::assertSame([], $rows);
    }

    public function testParseiaMultiplasLinhasDoBoletim(): void
    {
        $rows = $this->parser->parse(self::REAL_DOLQ26_LINE . "\n" . self::REAL_DOLZ26_LINE);

        self::assertCount(2, $rows);
    }
}
