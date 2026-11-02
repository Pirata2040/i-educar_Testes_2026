<?php

namespace App\Services;

use Illuminate\Support\Str;

class SimilarPersonService
{
    public function normalizeName(?string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower(Str::ascii($name ?? ''))));
    }
}
