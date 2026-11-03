# Testes de unidade de AgendaService::isValidTime()

Evidências dos testes caixa-preta e caixa-branca de `App\Services\AgendaService::isValidTime()`.

- Método: `app/Services/AgendaService.php` (`isValidTime()`, privado)
- Testes: `tests/Unit/Services/AgendaServiceIsValidTimeTest.php` (35 casos, sem banco)
- Branch: `testes-unidade-isvalidtime`

```php
private function isValidTime(?string $time): bool
{
    return (bool) preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', (string) $time);
}
```

O método valida um horário `hh:mm` entre 00:00 e 23:59. Ele é usado em `validate()` (hora de início do
compromisso), `saveNoteAsCommitment()` (hora de fim ao transformar uma anotação em compromisso) e
`storeCommitment()` (decide se grava `data_fim`). Como é privado, os testes o chamam por `ReflectionMethod`,
como o `AgendaServiceParseDateTest`.

## Casos de teste

| Grupo | Técnica | Casos |
|---|---|---|
| V1, V2 | particionamento: classes válidas (hora 00-19 e 20-23) | 2 |
| I1 a I12 | particionamento: classes inválidas | 14 |
| L1 a L8 | valor limite (00:00, 23:59, 24:00, 23:60, 00:59, 00:60, 19:59, 20:00) | 8 |
| B1 a B9 | MC/DC das condições da expressão regular (um par por condição) | 9 |
| defeito | quebra de linha no fim aceita pelo `$` | 2 |

## Cobertura antes e depois

`cobertura-antes/` mede o `AgendaService` só com os testes da agenda que já existiam
(`AgendaServiceTest`, `AgendaServiceParseDateTest`, `AgendaServiceFormatDateTDDTest` e os testes de request
`Agenda*`). `cobertura-depois/` acrescenta o `AgendaServiceIsValidTimeTest`.

| `isValidTime()` | Linhas | Branches | Caminhos |
|---|---|---|---|
| Antes (25 testes da agenda) | 1/1 (100%) | 3/4 (75%) | 1/2 (50%) |
| Depois (+35 casos) | 1/1 (100%) | 3/4 (75%) | 1/2 (50%) |

A cobertura estrutural não muda. Nenhum teste existente chama o método diretamente: ele é exercitado pela
tela da agenda (`AgendaTest`) e pelo `AgendaServiceTest`, com horários válidos (08:00 a 15:00) e três
inválidos (`'25:00'`, `'x'` e `''`). Isso já executa a única linha do método, mas não testa os limites
(00:00, 23:59, 24:00, 23:60), minutos acima de 59, variações de formato nem o nulo. A branch que falta é o desvio `JMP_FRAMELESS` que o PHP 8.4+ gera para procurar uma função
`App\Services\preg_match` antes de usar a nativa, e que nunca é tomado. Toda a lógica de validação está
dentro da expressão regular, que a cobertura do Xdebug não enxerga. Por isso o MC/DC foi aplicado às
condições da expressão.

## Defeito encontrado

Sem o modificador `D`, o `$` do PCRE também casa antes de uma quebra de linha final. `isValidTime("10:00\n")`
devolve `true`, e o valor chega a `storeCommitment()`, que o concatena em `data_fim`. O teste
`test_defeito_aceita_horario_com_quebra_de_linha_no_final` registra o comportamento atual, no mesmo padrão
dos testes "defeito:" do projeto. A correção seria usar `\z` ou o modificador `D` na expressão.

## Segundo método: AgendaService::validate() (começando pela caixa-branca)

- Testes: `tests/Unit/Services/AgendaServiceValidateTest.php` (26 casos, sem banco)

```php
private function validate(?string $title, ?string $description, ?string $date, ?string $startTime): string
{
    if (!$title && !$description) {                 // D1 = C1 && C2
        return self::ERROR_TITLE_OR_DESCRIPTION;
    }
    if ($this->parseDate($date) === null) {          // D2
        return self::ERROR_DATE;
    }
    if (!$this->isValidTime($startTime)) {           // D3
        return self::ERROR_START_TIME;
    }
    return '';
}
```

Aqui a ordem foi a inversa: o projeto começou pela estrutura do código e foi complementado com
caixa-preta.

| Grupo | Técnica | Casos |
|---|---|---|
| W1 a W6 | cobertura de decisões e de caminhos (D1, D2 e D3 verdadeiras e falsas) | 6 |
| M1 a M3 | MC/DC de D1 (C1 = sem título, C2 = sem descrição) | 3 |
| P1 a P14 | caixa-preta: classes dos campos, limites de data e hora e prioridade das mensagens | 14 |
| defeito | título ou descrição "0" tratados como vazios | 2 |
| observação | título só com espaços aceito pelo método | 1 |

| `validate()` | Linhas | Branches | Caminhos |
|---|---|---|---|
| Antes (testes existentes) | 7/7 (100%) | 9/9 (100%) | 4/8 (50%) |
| Depois | 7/7 (100%) | 9/9 (100%) | 7/8 (87,5%) |

Os testes existentes já cobriam todas as linhas e branches, mas não os caminhos em que só a descrição está
preenchida. O oitavo caminho é inviável: quando há título, o `&&` faz curto-circuito com resultado falso e
nunca chega ao `return` do erro de título.

Defeito: as condições de D1 usam a negação do PHP, que trata o texto `"0"` como falso. Um compromisso com
título `"0"` e sem descrição é recusado como se não tivesse título. A observação do título só com espaços
não é defeito na tela: ela lê os campos por `request()`, que passa pelo middleware `TrimStrings`.

Cobertura em `cobertura-validate-antes/` e `cobertura-validate-depois/`, gerada com
`phpunit-validate-antes.xml` e `phpunit-validate-depois.xml`.

## Como executar

Só os testes do método (não precisam de banco):

```bash
docker compose exec php vendor/bin/pest tests/Unit/Services/AgendaServiceIsValidTimeTest.php
```

Cobertura antes e depois (precisam do banco `testing`, conforme o `INSTALL.md`):

```bash
docker compose exec -e XDEBUG_MODE=coverage php php -d memory_limit=1G vendor/bin/pest -c phpunit-isvalidtime-antes.xml --path-coverage
```

```bash
docker compose exec -e XDEBUG_MODE=coverage php php -d memory_limit=1G vendor/bin/pest -c phpunit-isvalidtime-depois.xml --path-coverage
```

As duas configurações ficam na raiz do projeto porque o Pest só carrega os testes no estilo `test()` quando
a configuração está na raiz.
