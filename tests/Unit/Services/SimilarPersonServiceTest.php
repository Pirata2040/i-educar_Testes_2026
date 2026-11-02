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
}
