<?php

namespace Tests\Unit\Services;

use App\Services\SimilarPersonService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #1233: avisar pessoas parecidas antes de cadastrar uma nova pessoa física.
 *
 * Desenvolvido com TDD. Cada bloco de testes corresponde a um ciclo Red/Green/Refactor
 * (ver docs/tdd-1233).
 */
class SimilarPersonServiceTest extends TestCase
{
    private SimilarPersonService $service;

    protected function setUp(): void
    {
        $this->service = new SimilarPersonService;
    }

    /*
     * Ciclo 1 - normalizeName()
     * Regra: comparar nomes ignorando acento e maiúsculas (issue #1233).
     * Técnica: particionamento de equivalência sobre o texto de entrada.
     */

    #[DataProvider('nomesParaNormalizar')]
    public function test_nome_fica_sem_acento_maiusculas_ou_espacos_extras(?string $nome, string $esperado): void
    {
        // Arrange: o serviço é criado no setUp() e o nome vem do provedor de dados.

        // Act
        $normalizado = $this->service->normalizeName($nome);

        // Assert
        $this->assertSame($esperado, $normalizado);
    }

    public static function nomesParaNormalizar(): array
    {
        return [
            'já normalizado' => ['joao da silva', 'joao da silva'],
            'maiúsculas' => ['JOAO DA SILVA', 'joao da silva'],
            'maiúsculas e minúsculas misturadas' => ['JoAo Da SiLvA', 'joao da silva'],
            'acentos agudo, til e circunflexo' => ['Conceição Araújo Gonçalves', 'conceicao araujo goncalves'],
            'acentos em maiúsculas' => ['ÂNGELA ÉLLEN ÚRSULA', 'angela ellen ursula'],
            'trema e grave' => ['Müller À Vontade', 'muller a vontade'],
            'espaços nas pontas' => ['  Maria José  ', 'maria jose'],
            'espaços repetidos no meio' => ['Maria    José', 'maria jose'],
            'tabulação e quebra de linha' => ["Ana\tPaula\nSouza", 'ana paula souza'],
            'texto vazio' => ['', ''],
            'só espaços' => ['   ', ''],
            'nulo' => [null, ''],
        ];
    }

    /*
     * Ciclo 2 - isSimilar()
     * Regra: é parecida a pessoa com a mesma data de nascimento e nome parecido, ignorando
     * acento e maiúsculas. Data de nascimento vazia não dispara a busca (issue #1233).
     * Técnicas: particionamento de equivalência (parecida / não parecida / dados vazios) e
     * análise de valor limite na data (mesmo dia, um dia antes e um dia depois).
     */

    #[DataProvider('pessoasParecidas')]
    public function test_pessoa_com_mesma_data_e_mesmo_nome_normalizado_e_parecida(
        string $nome,
        string $outroNome
    ): void {
        // Arrange
        $dataNascimento = '2015-03-10';

        // Act
        $parecida = $this->service->isSimilar($nome, $dataNascimento, $outroNome, $dataNascimento);

        // Assert
        $this->assertTrue($parecida);
    }

    public static function pessoasParecidas(): array
    {
        return [
            'nomes idênticos' => ['João da Silva', 'João da Silva'],
            'diferença só de acento' => ['Joao da Silva', 'João da Silva'],
            'diferença só de maiúsculas' => ['JOÃO DA SILVA', 'joão da silva'],
            'diferença só de espaços' => [' João  da Silva ', 'João da Silva'],
        ];
    }

    #[DataProvider('pessoasDiferentes')]
    public function test_pessoa_com_nome_ou_data_diferente_nao_e_parecida(
        string $nome,
        string $dataNascimento,
        string $outroNome,
        string $outraDataNascimento
    ): void {
        // Arrange: os dados vêm do provedor.

        // Act
        $parecida = $this->service->isSimilar($nome, $dataNascimento, $outroNome, $outraDataNascimento);

        // Assert
        $this->assertFalse($parecida);
    }

    public static function pessoasDiferentes(): array
    {
        return [
            'mesma data e nome diferente' => ['João da Silva', '2015-03-10', 'Maria da Silva', '2015-03-10'],
            'mesmo nome e data diferente' => ['João da Silva', '2015-03-10', 'João da Silva', '2016-08-22'],
            'limite: nascida um dia depois' => ['João da Silva', '2015-03-10', 'João da Silva', '2015-03-11'],
            'limite: nascida um dia antes' => ['João da Silva', '2015-03-10', 'João da Silva', '2015-03-09'],
        ];
    }

    #[DataProvider('datasDeNascimentoVazias')]
    public function test_data_de_nascimento_vazia_nunca_torna_a_pessoa_parecida(
        ?string $dataNascimento,
        ?string $outraDataNascimento
    ): void {
        // Arrange
        $nome = 'João da Silva';

        // Act
        $parecida = $this->service->isSimilar($nome, $dataNascimento, $nome, $outraDataNascimento);

        // Assert
        $this->assertFalse($parecida);
    }

    public static function datasDeNascimentoVazias(): array
    {
        return [
            'data informada vazia' => ['', '2015-03-10'],
            'data informada nula' => [null, '2015-03-10'],
            'data do cadastro existente nula' => ['2015-03-10', null],
            'as duas datas nulas' => [null, null],
            'as duas datas vazias' => ['', ''],
        ];
    }

    #[DataProvider('nomesVazios')]
    public function test_nome_vazio_nunca_torna_a_pessoa_parecida(?string $nome, ?string $outroNome): void
    {
        // Arrange
        $dataNascimento = '2015-03-10';

        // Act
        $parecida = $this->service->isSimilar($nome, $dataNascimento, $outroNome, $dataNascimento);

        // Assert
        $this->assertFalse($parecida);
    }

    public static function nomesVazios(): array
    {
        return [
            'os dois nomes vazios' => ['', ''],
            'os dois nomes só com espaços' => ['  ', ' '],
            'os dois nomes nulos' => [null, null],
        ];
    }

    /*
     * Ciclo 3 - shouldWarn()
     * Regra (issue #1233): na inclusão, com data de nascimento preenchida, mostrar as pessoas
     * parecidas e não gravar até o usuário confirmar "não é nenhuma destas". Na edição, a lista
     * só aparece se o nome ou a data de nascimento passarem a coincidir com outra pessoa.
     * Técnica: particionamento de equivalência pelos cenários da issue. Os casos também foram
     * escolhidos para cobrir MC/DC da decisão (cada condição muda o resultado sozinha).
     */

    public function test_inclusao_com_pessoas_parecidas_exibe_o_aviso(): void
    {
        // Act
        $avisa = $this->service->shouldWarn(
            isNew: true,
            birthDate: '2015-03-10',
            hasSimilarPeople: true,
            confirmedNotSimilar: false,
            nameOrBirthDateChanged: false,
        );

        // Assert
        $this->assertTrue($avisa);
    }

    public function test_inclusao_sem_pessoas_parecidas_nao_exibe_o_aviso(): void
    {
        // Act
        $avisa = $this->service->shouldWarn(
            isNew: true,
            birthDate: '2015-03-10',
            hasSimilarPeople: false,
            confirmedNotSimilar: false,
            nameOrBirthDateChanged: false,
        );

        // Assert
        $this->assertFalse($avisa);
    }

    public function test_inclusao_confirmada_como_pessoa_nova_nao_exibe_o_aviso(): void
    {
        // Act
        $avisa = $this->service->shouldWarn(
            isNew: true,
            birthDate: '2015-03-10',
            hasSimilarPeople: true,
            confirmedNotSimilar: true,
            nameOrBirthDateChanged: false,
        );

        // Assert
        $this->assertFalse($avisa);
    }

    #[DataProvider('datasDeNascimentoNaoInformadas')]
    public function test_inclusao_sem_data_de_nascimento_nao_exibe_o_aviso(?string $dataNascimento): void
    {
        // Act
        $avisa = $this->service->shouldWarn(
            isNew: true,
            birthDate: $dataNascimento,
            hasSimilarPeople: true,
            confirmedNotSimilar: false,
            nameOrBirthDateChanged: false,
        );

        // Assert
        $this->assertFalse($avisa);
    }

    public static function datasDeNascimentoNaoInformadas(): array
    {
        return [
            'data vazia' => [''],
            'data nula' => [null],
        ];
    }

    public function test_edicao_sem_mudar_nome_nem_data_nao_exibe_o_aviso(): void
    {
        // Act
        $avisa = $this->service->shouldWarn(
            isNew: false,
            birthDate: '2015-03-10',
            hasSimilarPeople: true,
            confirmedNotSimilar: false,
            nameOrBirthDateChanged: false,
        );

        // Assert
        $this->assertFalse($avisa);
    }

    public function test_edicao_que_passa_a_coincidir_com_outra_pessoa_exibe_o_aviso(): void
    {
        // Act
        $avisa = $this->service->shouldWarn(
            isNew: false,
            birthDate: '2015-03-10',
            hasSimilarPeople: true,
            confirmedNotSimilar: false,
            nameOrBirthDateChanged: true,
        );

        // Assert
        $this->assertTrue($avisa);
    }

    public function test_edicao_que_passa_a_coincidir_mas_foi_confirmada_nao_exibe_o_aviso(): void
    {
        // Act
        $avisa = $this->service->shouldWarn(
            isNew: false,
            birthDate: '2015-03-10',
            hasSimilarPeople: true,
            confirmedNotSimilar: true,
            nameOrBirthDateChanged: true,
        );

        // Assert
        $this->assertFalse($avisa);
    }
}
