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

    public function isSimilar(?string $name, ?string $birthDate, ?string $otherName, ?string $otherBirthDate): bool
    {
        if (empty($birthDate) || empty($otherBirthDate)) {
            return false;
        }

        if ($this->normalizeName($name) === '') {
            return false;
        }

        return $birthDate === $otherBirthDate && $this->normalizeName($name) === $this->normalizeName($otherName);
    }
}
