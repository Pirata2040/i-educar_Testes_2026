<?php

namespace Tests\Unit\Services;

use App\Services\AgendaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use ReflectionMethod;

/**
 * Testes de unidade de AgendaService::validate().
 *
 * O método valida os dados de um compromisso da agenda antes de gravá-lo (createCommitment() e
 * updateCommitment()) e devolve a mensagem do primeiro erro encontrado, ou '' quando está tudo certo:
 *
 *   D1: if (!$title && !$description)           -> ERROR_TITLE_OR_DESCRIPTION
 *   D2: if ($this->parseDate($date) === null)   -> ERROR_DATE
 *   D3: if (!$this->isValidTime($startTime))    -> ERROR_START_TIME
 *       return ''
 *
 * Projeto dos casos: primeiro caixa-branca (cobertura de decisões e MC/DC da decisão D1, que tem duas
 * condições), depois caixa-preta (classes de equivalência dos campos e prioridade das mensagens). Os
 * identificadores entre colchetes (W1, M2, P3...) correspondem às tabelas do relatório.
 */
class AgendaServiceValidateTest extends TestCase
{
    private const DATA = '10/03/2030';

    private const HORA = '10:00';

    private AgendaService $service;

    private ReflectionMethod $validate;

    protected function setUp(): void
    {
        $this->service = new AgendaService;
        $this->validate = new ReflectionMethod(AgendaService::class, 'validate');
    }

    private function validate(?string $title, ?string $description, ?string $date, ?string $startTime): string
    {
        return $this->validate->invoke($this->service, $title, $description, $date, $startTime);
    }

    private static function erro(string $constante): string
    {
        return (new ReflectionClassConstant(AgendaService::class, $constante))->getValue();
    }

    /*
     * Caixa-branca - cobertura de decisões: cada decisão (D1, D2, D3) assume os valores verdadeiro e falso.
     * Quatro casos percorrem os quatro caminhos do método.
     */

    #[DataProvider('caminhosDoMetodo')]
    public function test_cada_decisao_devolve_a_sua_mensagem(
        ?string $titulo,
        ?string $descricao,
        ?string $data,
        ?string $hora,
        string $erroEsperado
    ): void {
        // Arrange
        $esperado = $erroEsperado === '' ? '' : self::erro($erroEsperado);

        // Act
        $mensagem = $this->validate($titulo, $descricao, $data, $hora);

        // Assert
        $this->assertSame($esperado, $mensagem);
    }

    public static function caminhosDoMetodo(): array
    {
        return [
            '[W1] D1 verdadeira: sem título e sem descrição' => [null, null, self::DATA, self::HORA, 'ERROR_TITLE_OR_DESCRIPTION'],
            '[W2] D1 falsa e D2 verdadeira: data inválida' => ['Reunião', null, '31/02/2030', self::HORA, 'ERROR_DATE'],
            '[W3] D2 falsa e D3 verdadeira: hora inválida' => ['Reunião', null, self::DATA, '25:00', 'ERROR_START_TIME'],
            '[W4] D3 falsa: dados válidos' => ['Reunião', null, self::DATA, self::HORA, ''],
            // D1 também é falsa quando só a descrição está preenchida (C1 verdadeira, C2 falsa). Esses casos
            // percorrem os caminhos que saem desse lado da decisão.
            '[W5] só descrição e data inválida' => [null, 'Pauta da reunião', '31/02/2030', self::HORA, 'ERROR_DATE'],
            '[W6] só descrição e hora inválida' => [null, 'Pauta da reunião', self::DATA, '25:00', 'ERROR_START_TIME'],
        ];
    }

    /*
     * Caixa-branca - MC/DC da decisão D1 = C1 && C2, com C1 = !$title e C2 = !$description.
     * M1 é o caso base (as duas verdadeiras); M2 muda só C1 e M3 muda só C2, e em ambos o resultado muda.
     */

    #[DataProvider('combinacoesDaDecisaoD1')]
    public function test_cada_condicao_de_d1_muda_o_resultado_sozinha(
        ?string $titulo,
        ?string $descricao,
        bool $d1Verdadeira
    ): void {
        // Act
        $mensagem = $this->validate($titulo, $descricao, self::DATA, self::HORA);

        // Assert
        $this->assertSame($d1Verdadeira ? self::erro('ERROR_TITLE_OR_DESCRIPTION') : '', $mensagem);
    }

    public static function combinacoesDaDecisaoD1(): array
    {
        return [
            '[M1] C1 e C2 verdadeiras: sem título e sem descrição' => [null, null, true],
            '[M2] só C1 falsa: com título e sem descrição' => ['Reunião', null, false],
            '[M3] só C2 falsa: sem título e com descrição' => [null, 'Pauta da reunião', false],
        ];
    }

    /*
     * Caixa-preta - complemento: classes de equivalência dos campos, a partir da mensagem de erro
     * ("Você deve preencher o campo Título ou o campo Descrição", "Data ... dd/mm/aaaa", "Hora ... hh:mm").
     */

    #[DataProvider('camposVazios')]
    public function test_titulo_e_descricao_vazios_sao_recusados(?string $titulo, ?string $descricao): void
    {
        // Act
        $mensagem = $this->validate($titulo, $descricao, self::DATA, self::HORA);

        // Assert
        $this->assertSame(self::erro('ERROR_TITLE_OR_DESCRIPTION'), $mensagem);
    }

    public static function camposVazios(): array
    {
        return [
            '[P1] os dois nulos' => [null, null],
            '[P2] os dois textos vazios' => ['', ''],
            '[P3] título vazio e descrição nula' => ['', null],
        ];
    }

    #[DataProvider('dadosValidos')]
    public function test_compromisso_com_dados_validos_nao_tem_erro(?string $titulo, ?string $descricao, string $data, string $hora): void
    {
        // Act
        $mensagem = $this->validate($titulo, $descricao, $data, $hora);

        // Assert
        $this->assertSame('', $mensagem);
    }

    public static function dadosValidos(): array
    {
        return [
            '[P4] título e descrição preenchidos' => ['Reunião', 'Pauta da reunião', self::DATA, self::HORA],
            '[P5] limite: 29/02 de ano bissexto' => ['Reunião', null, '29/02/2028', self::HORA],
            '[P6] limite: primeiro horário do dia' => ['Reunião', null, self::DATA, '00:00'],
            '[P7] limite: último horário do dia' => ['Reunião', null, self::DATA, '23:59'],
        ];
    }

    #[DataProvider('datasEHorasInvalidas')]
    public function test_data_ou_hora_invalida_devolve_a_mensagem_do_campo(?string $data, ?string $hora, string $erroEsperado): void
    {
        // Act
        $mensagem = $this->validate('Reunião', null, $data, $hora);

        // Assert
        $this->assertSame(self::erro($erroEsperado), $mensagem);
    }

    public static function datasEHorasInvalidas(): array
    {
        return [
            '[P8] data nula' => [null, self::HORA, 'ERROR_DATE'],
            '[P9] limite: 29/02 de ano não bissexto' => ['29/02/2030', self::HORA, 'ERROR_DATE'],
            '[P10] data no formato do banco' => ['2030-03-10', self::HORA, 'ERROR_DATE'],
            '[P11] hora nula' => [self::DATA, null, 'ERROR_START_TIME'],
            '[P12] limite: hora 24:00' => [self::DATA, '24:00', 'ERROR_START_TIME'],
        ];
    }

    /*
     * Caixa-preta - prioridade das mensagens quando mais de um campo está errado. A ordem das decisões no
     * código define qual erro aparece; a especificação não diz qual deveria ser.
     */

    #[DataProvider('variosCamposInvalidos')]
    public function test_com_varios_campos_invalidos_devolve_o_primeiro_erro(?string $titulo, ?string $data, ?string $hora, string $erroEsperado): void
    {
        // Act
        $mensagem = $this->validate($titulo, null, $data, $hora);

        // Assert
        $this->assertSame(self::erro($erroEsperado), $mensagem);
    }

    public static function variosCamposInvalidos(): array
    {
        return [
            '[P13] tudo inválido: vale o título' => [null, '31/02/2030', '25:00', 'ERROR_TITLE_OR_DESCRIPTION'],
            '[P14] data e hora inválidas: vale a data' => ['Reunião', '31/02/2030', '25:00', 'ERROR_DATE'],
        ];
    }

    /*
     * Caixa-preta - defeito e observação encontrados nas classes de "campo preenchido".
     *
     * As condições de D1 usam a negação do PHP (!$title), que trata o texto "0" como falso. Um compromisso
     * com o título "0" e sem descrição é recusado como se o título estivesse vazio. Um título só com espaços,
     * por outro lado, é aceito por validate(); na tela, o middleware TrimStrings do Laravel remove os espaços
     * antes de o valor chegar aqui.
     */

    public function test_defeito_titulo_0_e_tratado_como_vazio(): void
    {
        // Act
        $mensagem = $this->validate('0', null, self::DATA, self::HORA);

        // Assert
        $this->assertSame(self::erro('ERROR_TITLE_OR_DESCRIPTION'), $mensagem);
    }

    public function test_defeito_descricao_0_e_tratada_como_vazia(): void
    {
        // Act
        $mensagem = $this->validate(null, '0', self::DATA, self::HORA);

        // Assert
        $this->assertSame(self::erro('ERROR_TITLE_OR_DESCRIPTION'), $mensagem);
    }

    public function test_titulo_so_com_espacos_e_aceito_pelo_metodo(): void
    {
        // Act
        $mensagem = $this->validate('   ', null, self::DATA, self::HORA);

        // Assert
        $this->assertSame('', $mensagem);
    }
}
