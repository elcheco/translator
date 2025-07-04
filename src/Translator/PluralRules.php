<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
 * @created  20/03/25 19:06
 */

declare(strict_types=1);

namespace ElCheco\Translator;

class PluralRules implements PluralRulesInterface
{
    /**
     * Languages with tens repetition pattern
     * @var array<string, bool>
     */
    private const TENS_REPEATING_LANGUAGES = [
        'ru' => true,    // Russian
        'uk' => true,    // Ukrainian
        'be' => true,    // Belarusian
        'sr' => true,    // Serbian
        'hr' => true,    // Croatian
        'bs' => true,    // Bosnian
        'cnr' => true,   // Montenegrin
    ];

    /**
     * Languages with simple plural forms
     * @var array<string, bool>
     */
    private const SIMPLE_PLURAL_LANGUAGES = [
        'pl' => true,    // Polish
        'cs' => true,    // Czech
        'sk' => true,    // Slovak
        'bg' => true,    // Bulgarian
        'mk' => true,    // Macedonian
        'ro' => true,    // Romanian
        'hu' => true,    // Hungarian
    ];

    /**
     * Languages with special dual form
     * @var array<string, bool>
     */
    private const DUAL_FORM_LANGUAGES = [
        'sl' => true,    // Slovene
    ];

    /**
     * Languages with western plural form (singular/plural only)
     * @var array<string, bool>
     */
    private const WESTERN_PLURAL_LANGUAGES = [
        'nl' => true,    // Dutch
        'es' => true,    // Spanish
        'sv' => true,    // Swedish
        'pt' => true,    // Portuguese
        'da' => true,    // Danish
        'no' => true,    // Norwegian
        'nb' => true,    // Norwegian Bokmål
        'nn' => true,    // Norwegian Nynorsk
    ];

    public static function getNormalizedCount(string $locale, int $count): int
    {
        $lang = strtolower(substr($locale, 0, strpos($locale . '_', '_')));

        if (isset(self::TENS_REPEATING_LANGUAGES[$lang])) {
            return self::getTensRepeatingForm($count);
        }

        if (isset(self::SIMPLE_PLURAL_LANGUAGES[$lang])) {
            return self::getSimplePluralForm($count);
        }

        if (isset(self::DUAL_FORM_LANGUAGES[$lang])) {
            return self::getDualForm($count);
        }

        if (isset(self::WESTERN_PLURAL_LANGUAGES[$lang])) {
            return self::getWesternPluralForm($count);
        }

        return $count;
    }

    private static function getTensRepeatingForm(int $count): int
    {
        if ($count === 0) {
            return 0;
        }

        $mod10 = $count % 10;
        $mod100 = $count % 100;

        // Check for 11-19, they use the plural form
        if ($mod100 >= 11 && $mod100 <= 19) {
            return 5;
        }

        // For 21, 31, 41, etc. (those ending with 1 but not 11) use the singular form
        if ($mod10 === 1) {
            return 1;
        }

        // For 2-4, 22-24, 32-34, etc. use the dual form
        if ($mod10 >= 2 && $mod10 <= 4) {
            return 2;
        }

        // For everything else, use the plural form
        return 5;
    }

    private static function getSimplePluralForm(int $count): int
    {
        if ($count === 0) {
            return 0;
        }

        return match ($count) {
            1 => 1,
            2, 3, 4 => 2,
            default => 5,
        };
    }

    private static function getDualForm(int $count): int
    {
        if ($count === 0) {
            return 0;
        }

        return match ($count) {
            1 => 1,
            2 => 2,
            3, 4 => 3, // Slovenian has a special form for 3-4
            default => 5,
        };
    }

    private static function getWesternPluralForm(int $count): int
    {
        if ($count === 0) {
            return 0;
        }

        return match ($count) {
            1 => 1,
            default => 2,
        };
    }
}
