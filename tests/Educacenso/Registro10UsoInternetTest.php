
<?php

use App\Models\Educacenso\Registro10;
use iEducar\Modules\Educacenso\Model\UsoInternet;

// CAIXA-PRETA
// Particionamento de equivalência e valores-limite

test('caixa-preta: validacao do uso de internet', function (
    array $opcoes,
    bool $esperado
) {
    $registro = new Registro10;
    $registro->usoInternet = $opcoes;

    expect(
        $registro->usoInternetInexistenteEOutrosCamposPreenchidos()
    )->toBe($esperado);
})->with([
    'CP01 - nenhuma opcao' => [
        [], false
    ],
    'CP02 - apenas nao possui' => [
        [UsoInternet::NAO_POSSUI], false
    ],
    'CP03 - apenas alunos' => [
        [UsoInternet::ALUNOS], false
    ],
    'CP04 - nao possui e alunos' => [
        [UsoInternet::NAO_POSSUI, UsoInternet::ALUNOS],
        true
    ],
    'CP05 - nao possui e mais duas opcoes' => [
        [
            UsoInternet::NAO_POSSUI,
            UsoInternet::ADMINISTRATIVO,
            UsoInternet::ALUNOS
        ],
        true
    ],
]);

test('caixa-branca: MC/DC condicao A falsa e B verdadeira', function () {
    $registro = new Registro10;

    $registro->usoInternet = [
        UsoInternet::ADMINISTRATIVO,
        UsoInternet::ALUNOS
    ];

    expect(
        $registro->usoInternetInexistenteEOutrosCamposPreenchidos()
    )->toBeFalse();
});
