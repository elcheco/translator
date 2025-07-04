<?php

declare(strict_types=1);

namespace ElCheco\Translator;

interface PluralRulesInterface
{
    /**
     * Get normalized count for pluralization based on locale
     */
    public static function getNormalizedCount(string $locale, int $count): int;
}
