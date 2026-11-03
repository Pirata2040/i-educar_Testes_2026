<?php

namespace Tests\Unit\Services;

use App\Services\AgendaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Testes de unidade de AgendaService::isValidTime().
 *
 * O método valida o horário no formato hh:mm (00:00 a 23:59) e é usado na agenda para a hora de início de
 * um compromisso (validate()), para a hora de fim ao transformar uma anotação em compromisso
 * (saveNoteAsCommitment()) e para decidir se a hora de fim é gravada (storeCommitment()).
 *
 * Projeto dos casos: primeiro caixa-preta (particionamento de equivalência e análise de valor limite),
 * depois caixa-branca sobre a expressão regular (MC/DC das condições). Os identificadores entre colchetes
 * (V1, I3, B2...) correspondem às tabelas do relatório.
 */
class AgendaServiceIsValidTimeTest extends TestCase
{
    private AgendaService $service;

    private ReflectionMethod $isValidTime;

    protected function setUp(): void
    {
        $this->service = new AgendaService;
        $this->isValidTime = new ReflectionMethod(AgendaService::class, 'isValidTime');
    }

    private function isValidTime(?string $time): bool
    {
        return $this->isValidTime->invoke($this->service, $time);
    }

    /*
     * Caixa-preta - particionamento de equivalência: classes válidas.
     */

    #[DataProvider('horariosDeClassesValidas')]
    public function test_horario_de_classe_valida_e_aceito(string $horario): void
    {
        // Act
        $valido = $this->isValidTime($horario);

        // Assert
        $this->assertTrue($valido);
    }

    public static function horariosDeClassesValidas(): array
    {
        return [
            '[V1] hora de 00 a 19' => ['09:30'],
            '[V2] hora de 20 a 23' => ['21:45'],
        ];
    }

    /*
     * Caixa-preta - particionamento de equivalência: classes inválidas.
     */

    #[DataProvider('horariosDeClassesInvalidas')]
    public function test_horario_de_classe_invalida_e_recusado(?string $horario): void
    {
        // Act
        $valido = $this->isValidTime($horario);

        // Assert
        $this->assertFalse($valido);
    }

    public static function horariosDeClassesInvalidas(): array
    {
        return [
            '[I1] nulo' => [null],
            '[I2] texto vazio' => [''],
            '[I3] sem separador' => ['0930'],
            '[I4] separador h' => ['09h30'],
            '[I4] separador ponto' => ['09.30'],
            '[I5] hora com um dígito' => ['9:30'],
            '[I6] minuto com um dígito' => ['09:3'],
            '[I7] com segundos' => ['09:30:00'],
            '[I8] letras no lugar dos números' => ['ab:cd'],
            '[I9] espaço antes' => [' 09:30'],
            '[I9] espaço depois' => ['09:30 '],
            '[I10] hora negativa' => ['-1:30'],
            '[I11] hora acima de 23' => ['30:00'],
            '[I12] minuto acima de 59' => ['10:75'],
        ];
    }

    /*
     * Caixa-preta - análise de valor limite: hora entre 00 e 23 e minuto entre 00 e 59.
     */

    #[DataProvider('horariosNosLimites')]
    public function test_horario_nos_limites_do_intervalo(string $horario, bool $esperado): void
    {
        // Act
        $valido = $this->isValidTime($horario);

        // Assert
        $this->assertSame($esperado, $valido);
    }

    public static function horariosNosLimites(): array
    {
        return [
            '[L1] primeiro horário do dia' => ['00:00', true],
            '[L2] último horário do dia' => ['23:59', true],
            '[L3] hora logo acima do limite' => ['24:00', false],
            '[L4] minuto logo acima do limite' => ['23:60', false],
            '[L5] último minuto da primeira hora' => ['00:59', true],
            '[L6] minuto 60 na primeira hora' => ['00:60', false],
            '[L7] última hora da faixa 00-19' => ['19:59', true],
            '[L8] primeira hora da faixa 20-23' => ['20:00', true],
        ];
    }

    /*
     * Caixa-branca - MC/DC da expressão regular /^([01][0-9]|2[0-3]):[0-5][0-9]$/.
     *
     * A decisão é ((A e B) ou (C e D)) e E e F e G e H, com:
     *   A: 1º dígito da hora é 0 ou 1      B: 2º dígito da hora é 0-9
     *   C: 1º dígito da hora é 2           D: 2º dígito da hora é 0-3
     *   E: separador é ':'                 F: 1º dígito do minuto é 0-5
     *   G: 2º dígito do minuto é 0-9       H: a string termina logo após os minutos
     * A e C dependem do mesmo caractere, então não variam sozinhas: os pares de A e de C trocam o
     * 1º dígito por um valor que torna as duas falsas (MC/DC com mascaramento).
     */

    #[DataProvider('paresDeIndependencia')]
    public function test_cada_condicao_da_expressao_muda_o_resultado_sozinha(string $verdadeiro, string $falso): void
    {
        // Act
        $comCondicaoVerdadeira = $this->isValidTime($verdadeiro);
        $comCondicaoFalsa = $this->isValidTime($falso);

        // Assert
        $this->assertTrue($comCondicaoVerdadeira);
        $this->assertFalse($comCondicaoFalsa);
    }

    public static function paresDeIndependencia(): array
    {
        return [
            '[B1] A: 1º dígito da hora 0 ou 1' => ['13:00', '33:00'],
            '[B2] B: 2º dígito da hora numérico' => ['13:00', '1a:00'],
            '[B3] C: 1º dígito da hora 2' => ['23:00', '33:00'],
            '[B4] D: 2º dígito da hora 0-3' => ['23:00', '24:00'],
            '[B5] E: separador dois-pontos' => ['23:00', '23-00'],
            '[B6] F: 1º dígito do minuto 0-5' => ['23:59', '23:69'],
            '[B7] G: 2º dígito do minuto numérico' => ['23:59', '23:5a'],
            '[B8] H: nada depois dos minutos' => ['23:59', '23:590'],
            '[B9] início: nada antes da hora' => ['23:59', 'x23:59'],
        ];
    }

    /*
     * Caixa-branca - defeito encontrado na análise da expressão regular.
     *
     * Sem o modificador D, o $ do PCRE também casa antes de uma quebra de linha no fim do texto. Um horário
     * com "\n" no final passa na validação e chega a storeCommitment(), que o concatena em data_fim. O teste
     * registra o comportamento atual; o esperado pela especificação (hh:mm) seria recusar.
     */

    public function test_defeito_aceita_horario_com_quebra_de_linha_no_final(): void
    {
        // Act
        $valido = $this->isValidTime("10:00\n");

        // Assert
        $this->assertTrue($valido);
    }

    public function test_horario_com_duas_quebras_de_linha_no_final_e_recusado(): void
    {
        // Act
        $valido = $this->isValidTime("10:00\n\n");

        // Assert
        $this->assertFalse($valido);
    }
}
