<?php

namespace App\Support;

final class SearchTerm
{
    // The caller uses a bound parameter with LIKE ... ESCAPE '!'.
    public static function contains(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($term)).'%';
    }
}
