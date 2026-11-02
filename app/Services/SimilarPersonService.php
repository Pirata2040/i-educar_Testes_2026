<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Identifica pessoas físicas parecidas antes de gravar um novo cadastro (issue #1233).
 */
class SimilarPersonService
{
    /**
     * Deixa o nome em uma forma comparável: sem acento, em minúsculas e com um único espaço entre as palavras.
     * Serve só para comparar; o nome gravado não é alterado.
     */
    public function normalizeName(?string $name): string
    {
        $withoutAccents = Str::ascii($name ?? '');
        $lowercase = strtolower($withoutAccents);
        $singleSpaced = preg_replace('/\s+/', ' ', $lowercase);

        return trim($singleSpaced);
    }

    /**
     * Duas pessoas são parecidas quando nasceram no mesmo dia e têm o mesmo nome normalizado.
     * Sem data de nascimento ou sem nome não há como comparar, então nunca são parecidas.
     *
     * @param string|null $birthDate      data no formato Y-m-d
     * @param string|null $otherBirthDate data no formato Y-m-d
     */
    public function isSimilar(?string $name, ?string $birthDate, ?string $otherName, ?string $otherBirthDate): bool
    {
        $normalizedName = $this->normalizeName($name);

        if (empty($birthDate) || $normalizedName === '') {
            return false;
        }

        return $birthDate === $otherBirthDate && $normalizedName === $this->normalizeName($otherName);
    }

    /**
     * Decide se o cadastro deve parar para mostrar as pessoas parecidas antes de gravar.
     *
     * Não avisa quando não há data de nascimento, quando ninguém parecido foi encontrado ou quando o
     * usuário já confirmou que não é nenhuma delas. Fora isso, avisa sempre na inclusão e, na edição,
     * só se o nome ou a data de nascimento mudaram.
     */
    public function shouldWarn(
        bool $isNew,
        ?string $birthDate,
        bool $hasSimilarPeople,
        bool $confirmedNotSimilar,
        bool $nameOrBirthDateChanged
    ): bool {
        if (empty($birthDate) || !$hasSimilarPeople || $confirmedNotSimilar) {
            return false;
        }

        return $isNew || $nameOrBirthDateChanged;
    }
}
