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
    public function test_normaliza_o_nome_para_comparacao(?string $nome, string $esperado): void
    {
        $this->assertSame($esperado, $this->service->normalizeName($nome));
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
