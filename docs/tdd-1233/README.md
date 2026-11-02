# TDD da issue #1233: avisar pessoas parecidas antes de cadastrar

Evidências do desenvolvimento orientado a testes do `App\Services\SimilarPersonService`, feito para a
[issue #1233](https://github.com/portabilis/i-educar/issues/1233) do i-Educar.

- Código: `app/Services/SimilarPersonService.php`
- Testes: `tests/Unit/Services/SimilarPersonServiceTest.php`
- Branch: `tdd-issue-1233`

## Ciclos

| Ciclo | Comportamento | Casos | Técnicas | Commits (RED, GREEN, REFACTOR) |
|---|---|---|---|---|
| 1 | `normalizeName()`: nome sem acento, em minúsculas e com espaços únicos | 12 | particionamento de equivalência | `5eb2af211`, `0f91ec227`, `db4d12022` |
| 2 | `isSimilar()`: mesma data de nascimento e mesmo nome normalizado | 16 | particionamento e valor limite (um dia antes e depois) | `909b5e533`, `70dd719b0`, `a0cdf7d83` |
| 3 | `shouldWarn()`: quando parar o cadastro para mostrar as pessoas parecidas | 8 | cenários da issue, escolhidos para cobrir MC/DC | `3494d12d0`, `2a3e06503`, `1c61ecb75` |

Cada pasta `ciclo-N/` guarda a saída dos testes em cada etapa:

- `1-red.txt`: o teste novo falha (o método ainda não existe);
- `2-green.txt`: a implementação mínima faz todos passarem;
- `3-refactor.txt`: depois da refatoração, todos continuam passando. Nos ciclos 2 e 3 é a suíte completa do projeto.

`linha-de-base/suite-completa.txt` é a suíte completa antes do ciclo 2. Ela serve de referência para
mostrar que os ciclos não criaram falhas novas.

| Execução da suíte completa | Passaram | Falharam |
|---|---|---|
| Linha de base | 1767 | 73 |
| REFACTOR do ciclo 2 | 1796 | 60 |
| REFACTOR do ciclo 3 | 1804 | 60 |

As falhas já existiam no projeto, em testes que não fazem parte desta issue (49 `QueryException` e
15 `UniqueConstraintViolationException` na linha de base). Quatro arquivos falharam na linha de base e
passaram depois sem nenhuma mudança neles, o que mostra que essas falhas antigas são instáveis.

## Cobertura

Relatório em `cobertura/` (HTML em `cobertura/html/index.html`, texto em `cobertura/cobertura.txt`,
Clover em `cobertura/clover.xml`), medido só sobre o `SimilarPersonService`:

| Método | Linhas | Branches | Caminhos |
|---|---|---|---|
| `normalizeName()` | 4/4 (100%) | 5/7 (71,43%) | 1/4 (25%) |
| `isSimilar()` | 4/4 (100%) | 7/7 (100%) | 4/6 (66,67%) |
| `shouldWarn()` | 3/3 (100%) | 9/9 (100%) | 5/12 (41,67%) |
| **Total** | **11/11 (100%)** | **21/23 (91,30%)** | **10/22 (45,45%)** |

As 2 branches descobertas de `normalizeName()` não são falta de teste. A partir do PHP 8.4, chamadas sem
barra a `preg_replace()` e `trim()` dentro de um namespace viram um desvio `JMP_FRAMELESS`, que verifica se
existe uma função `App\Services\preg_replace` ou `App\Services\trim` antes de usar a nativa. Essas funções
não existem, então o desvio nunca é tomado. O dump de opcodes confirma:

```
0014 JMP_FRAMELESS 48 string("app\\services\\preg_replace") 0021
0025 JMP_FRAMELESS 64 string("app\\services\\trim") 0030
```

A cobertura de caminhos fica abaixo de 100% porque o Xdebug conta todas as combinações dos operadores
`&&` e `||`, inclusive as que o curto-circuito torna impossíveis.

## Como executar

Testes do serviço (não precisam de banco):

```bash
docker compose exec php vendor/bin/pest tests/Unit/Services/SimilarPersonServiceTest.php
```

Suíte completa (precisa do banco `testing` preparado conforme o `INSTALL.md`):

```bash
docker compose exec -e XDEBUG_MODE=off php vendor/bin/pest
```

Relatório de cobertura (gera a pasta `cobertura/`):

```bash
docker compose exec -e XDEBUG_MODE=coverage php php -d memory_limit=1G vendor/bin/pest -c docs/tdd-1233/phpunit-cobertura.xml --path-coverage
```

A configuração `phpunit-cobertura.xml` existe porque o `phpunit.xml` do projeto mede `app/`, `ieducar/` e
`src/` inteiros, o que gera um relatório de mais de 200 MB.
