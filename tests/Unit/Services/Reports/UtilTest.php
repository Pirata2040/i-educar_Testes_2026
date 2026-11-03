<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários de Util::formatWorkload()
 *
 * Requisito (RF-01): converter carga horária decimal (ex.: 1.5)
 * para texto no formato de relógio (ex.: "01:30").
 *
 * Abordagem: começa com CAIXA-PRETA e complementa com CAIXA-BRANCA.
 */
class UtilTest extends TestCase
{
    /* =========================================================
     * PARTE 1 - CAIXA-PRETA
     * Técnicas: Particionamento de Equivalência (PE)
     *           Análise de Valor Limite (AVL)
     * ========================================================= */

    /**
     * Técnica: Particionamento de Equivalência
     * Rastreabilidade: RF-01 -> CT-PE-01..06
     */
    #[DataProvider('particoesEquivalencia')]
    public function testParticionamentoDeEquivalencia(?float $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Util::formatWorkload($entrada));
    }

    public static function particoesEquivalencia(): array
    {
        return [
            'CT-PE-01 CE1 nulo'                => [null,  '00:00'],
            'CT-PE-02 CE2 zero'                => [0.0,   '00:00'],
            'CT-PE-03 CE3 inteiro positivo'    => [8.0,   '08:00'],
            'CT-PE-04 CE4 fracionado positivo' => [1.5,   '01:30'],
            'CT-PE-05 CE3 valor grande'        => [100.0, '100:00'],
            'CT-PE-06 CE4 fração pequena'      => [0.25,  '00:15'],
        ];
    }

    /**
     * CT-PE-07 - Classe inválida CE7: valor não numérico.
     * O tipo ?float faz o PHP lançar TypeError.
     */
    public function testEntradaNaoNumericaLancaTypeError(): void
    {
        $this->expectException(\TypeError::class);
        Util::formatWorkload('abc');
    }

    /**
     * Técnica: Análise de Valor Limite
     * Rastreabilidade: RF-01 -> CT-AVL-01..08
     */
    #[DataProvider('valoresLimite')]
    public function testAnaliseDeValorLimite(float $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Util::formatWorkload($entrada));
    }

    public static function valoresLimite(): array
    {
        return [
            'CT-AVL-01 menor fração que vira 1 min'         => [0.01,   '00:01'],
            'CT-AVL-02 logo abaixo de 0,5 min (p/ baixo)'   => [0.0083, '00:00'],
            'CT-AVL-03 logo acima de 0,5 min (p/ cima)'     => [0.0084, '00:01'],
            'CT-AVL-04 59 minutos (limite inferior do 60)'  => [1.9916, '01:59'],
            'CT-AVL-05 59,5 min vira 60 (limite superior)'  => [1.9917, '02:00'],
            'CT-AVL-06 quase 1 hora'                        => [0.9999, '01:00'],
            'CT-AVL-07 59 minutos aproximados'              => [1.99,   '01:59'],
            'CT-AVL-08 inteiro exato'                       => [2.0,    '02:00'],
        ];
    }

    /* =========================================================
     * PARTE 2 - CAIXA-BRANCA (complemento)
     * Técnicas: Cobertura de Decisões (branches) e MC/DC
     * ========================================================= */

    /** D1: if ($workload !== null) -> ramo FALSO (CT-CB-01) */
    public function testD1RamoFalsoQuandoNulo(): void
    {
        $this->assertSame('00:00', Util::formatWorkload(null));
    }

    /** D1: ramo VERDADEIRO (CT-CB-02) */
    public function testD1RamoVerdadeiroQuandoNaoNulo(): void
    {
        $this->assertSame('03:00', Util::formatWorkload(3.0));
    }

    /** D2: if ($minutes === 60) -> ramo VERDADEIRO (CT-CB-03) */
    public function testD2RamoVerdadeiroQuandoMinutosViram60(): void
    {
        $this->assertSame('01:00', Util::formatWorkload(0.9999));
    }

    /** D2: ramo FALSO (CT-CB-04) */
    public function testD2RamoFalsoQuandoMinutosMenoresQue60(): void
    {
        $this->assertSame('01:30', Util::formatWorkload(1.5));
    }

    /*
     * MC/DC: D1 e D2 têm uma única condição cada (sem && nem ||).
     * O MC/DC coincide com a cobertura de decisões (CT-CB-01..04).
     */

    /* =========================================================
     * PARTE 3 - INTEGRAÇÃO (caixa-preta + caixa-branca)
     * ========================================================= */

    /**
     * CT-INT-01 - Classe CE6 (negativo, não especificado no requisito).
     * Teste de caracterização: documenta o comportamento real atual.
     */
    public function testValorNegativoNaoTemTratamentoEspecifico(): void
    {
        $this->assertSame('-1:-30', Util::formatWorkload(-1.5));
    }
}
