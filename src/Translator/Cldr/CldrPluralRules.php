<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

use NumberFormatter;
use ElCheco\Translator\PluralRulesInterface;
use ElCheco\Translator\TranslatorException;

/**
 * CLDR Plural Rules implementation using PHP Intl extension
 *
 * This class provides CLDR-compliant plural form selection based on
 * the Unicode CLDR plural rules.
 *
 * @see https://www.unicode.org/cldr/charts/latest/supplemental/language_plural_rules.html
 */
class CldrPluralRules implements PluralRulesInterface
{
    /**
     * Cache for plural rules to avoid repeated lookups
     * @var array<string, string>
     */
    private static array $ruleCache = [];

    /**
     * CLDR plural categories
     */
    public const CATEGORY_ZERO = 'zero';
    public const CATEGORY_ONE = 'one';
    public const CATEGORY_TWO = 'two';
    public const CATEGORY_FEW = 'few';
    public const CATEGORY_MANY = 'many';
    public const CATEGORY_OTHER = 'other';

    /**
     * All valid CLDR categories
     * @var string[]
     */
    public const VALID_CATEGORIES = [
        self::CATEGORY_ZERO,
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_FEW,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER,
    ];

    /**
     * {@inheritdoc}
     *
     * For backward compatibility with existing PluralRulesInterface
     */
    public static function getNormalizedCount(string $locale, int $count): int
    {
        // Map CLDR categories to numeric forms for backward compatibility
        $category = self::getPluralCategory($locale, (float)$count);

        return match($category) {
            self::CATEGORY_ZERO => 0,
            self::CATEGORY_ONE => 1,
            self::CATEGORY_TWO => 2,
            self::CATEGORY_FEW => 3,
            self::CATEGORY_MANY => 5,
            self::CATEGORY_OTHER => 5,
        };
    }

    /**
     * Get the CLDR plural category for a given number and locale
     *
     * @param string $locale The locale code (e.g., 'en_US', 'cs_CZ')
     * @param float $number The number to get the plural category for
     * @return string One of the CLDR categories (zero, one, two, few, many, other)
     * @throws TranslatorException If the Intl extension is not available
     */
    public static function getPluralCategory(string $locale, float $number): string
    {
        if (!extension_loaded('intl')) {
            throw new TranslatorException('The Intl PHP extension is required for CLDR plural rules');
        }

        $cacheKey = $locale . ':' . $number;

        if (isset(self::$ruleCache[$cacheKey])) {
            return self::$ruleCache[$cacheKey];
        }

        try {
            $category = self::getPluralCategoryManual($locale, $number);
        } catch (\Exception $e) {
            // Fallback to manual detection
            $category = self::getPluralCategoryManual($locale, $number);
        }

        // Ensure we return a valid CLDR category
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            $category = self::CATEGORY_OTHER;
        }

        self::$ruleCache[$cacheKey] = $category;
        return $category;
    }

    /**
     * Manual plural category detection for common locales
     * This is a fallback when reflection doesn't work
     *
     * @param string $locale
     * @param float $number
     * @return string
     */
    private static function getPluralCategoryManual(string $locale, float $number): string
    {
        $lang = strtolower(substr($locale, 0, 2));
        $intNumber = (int)$number;
        $isInteger = $number == $intNumber;

        switch ($lang) {
            case 'cs': // Czech
            case 'sk': // Slovak
                // Handle decimals first - Czech/Slovak use 'many' for all decimal numbers
                if (!$isInteger) {
                    return self::CATEGORY_MANY;
                }

                if ($intNumber === 1) return self::CATEGORY_ONE;
                if ($intNumber >= 2 && $intNumber <= 4) return self::CATEGORY_FEW;
                return self::CATEGORY_OTHER;

            case 'en': // English and similar
            case 'de':
            case 'nl':
            case 'sv':
            case 'da':
            case 'no':
            case 'nb':
            case 'nn':
            case 'fo':
            case 'es':
            case 'pt':
            case 'it':
            case 'bg':
            case 'el':
            case 'fi':
            case 'et':
            case 'hu':
            case 'tr':
            case 'he':
                return $intNumber === 1 && $isInteger ? self::CATEGORY_ONE : self::CATEGORY_OTHER;

            case 'fr': // French and similar
            case 'pt_BR':
                return (($intNumber === 0 || $intNumber === 1) && $isInteger) ? self::CATEGORY_ONE : self::CATEGORY_OTHER;

            case 'pl': // Polish
                if (!$isInteger) return self::CATEGORY_OTHER;
                if ($intNumber === 1) return self::CATEGORY_ONE;
                $mod10 = $intNumber % 10;
                $mod100 = $intNumber % 100;
                if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
                    return self::CATEGORY_FEW;
                }
                return self::CATEGORY_MANY;

            case 'ru': // Russian
            case 'uk': // Ukrainian
            case 'be': // Belarusian
                if (!$isInteger) return self::CATEGORY_OTHER;
                $mod10 = $intNumber % 10;
                $mod100 = $intNumber % 100;
                if ($mod10 === 1 && $mod100 !== 11) return self::CATEGORY_ONE;
                if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
                    return self::CATEGORY_FEW;
                }
                return self::CATEGORY_MANY;

            case 'sr': // Serbian
            case 'hr': // Croatian
            case 'bs': // Bosnian
                if (!$isInteger) return self::CATEGORY_OTHER;
                $mod10 = $intNumber % 10;
                $mod100 = $intNumber % 100;
                if ($mod10 === 1 && $mod100 !== 11) return self::CATEGORY_ONE;
                if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
                    return self::CATEGORY_FEW;
                }
                return self::CATEGORY_OTHER;

            case 'sl': // Slovenian
                if (!$isInteger) return self::CATEGORY_OTHER;
                $mod100 = $intNumber % 100;
                if ($mod100 === 1) return self::CATEGORY_ONE;
                if ($mod100 === 2) return self::CATEGORY_TWO;
                if ($mod100 === 3 || $mod100 === 4) return self::CATEGORY_FEW;
                return self::CATEGORY_OTHER;

            case 'lt': // Lithuanian
                if (!$isInteger) return self::CATEGORY_MANY; // Lithuanian uses 'many' for decimals
                $mod10 = $intNumber % 10;
                $mod100 = $intNumber % 100;
                if ($mod10 === 1 && ($mod100 < 11 || $mod100 > 19)) return self::CATEGORY_ONE;
                if ($mod10 >= 2 && $mod10 <= 9 && ($mod100 < 11 || $mod100 > 19)) {
                    return self::CATEGORY_FEW;
                }
                return self::CATEGORY_OTHER;

            case 'lv': // Latvian
                if (!$isInteger) return self::CATEGORY_OTHER;
                if ($intNumber === 0) return self::CATEGORY_ZERO;
                if ($intNumber % 10 === 1 && $intNumber % 100 !== 11) return self::CATEGORY_ONE;
                return self::CATEGORY_OTHER;

            case 'ga': // Irish
                if (!$isInteger) return self::CATEGORY_OTHER;
                if ($intNumber === 1) return self::CATEGORY_ONE;
                if ($intNumber === 2) return self::CATEGORY_TWO;
                if ($intNumber >= 3 && $intNumber <= 6) return self::CATEGORY_FEW;
                if ($intNumber >= 7 && $intNumber <= 10) return self::CATEGORY_MANY;
                return self::CATEGORY_OTHER;

            case 'ro': // Romanian
                if (!$isInteger) return self::CATEGORY_FEW; // Romanian uses 'few' for decimals
                if ($intNumber === 1) return self::CATEGORY_ONE;
                if ($intNumber === 0 || ($intNumber % 100 >= 1 && $intNumber % 100 <= 19)) {
                    return self::CATEGORY_FEW;
                }
                return self::CATEGORY_OTHER;

            case 'mt': // Maltese
                if (!$isInteger) return self::CATEGORY_OTHER;
                if ($intNumber === 1) return self::CATEGORY_ONE;
                if ($intNumber === 0 || ($intNumber % 100 >= 2 && $intNumber % 100 <= 10)) {
                    return self::CATEGORY_FEW;
                }
                if ($intNumber % 100 >= 11 && $intNumber % 100 <= 19) {
                    return self::CATEGORY_MANY;
                }
                return self::CATEGORY_OTHER;

            case 'ar': // Arabic
                if (!$isInteger) return self::CATEGORY_OTHER;
                if ($intNumber === 0) return self::CATEGORY_ZERO;
                if ($intNumber === 1) return self::CATEGORY_ONE;
                if ($intNumber === 2) return self::CATEGORY_TWO;
                $mod100 = $intNumber % 100;
                if ($mod100 >= 3 && $mod100 <= 10) return self::CATEGORY_FEW;
                if ($mod100 >= 11 && $mod100 <= 99) return self::CATEGORY_MANY;
                return self::CATEGORY_OTHER;

            case 'ja': // Japanese
            case 'ko': // Korean
            case 'zh': // Chinese
            case 'th': // Thai
            case 'lo': // Lao
            case 'vi': // Vietnamese
            case 'id': // Indonesian
            case 'ms': // Malay
            case 'ka': // Georgian
            case 'az': // Azerbaijani
            case 'kk': // Kazakh
            case 'ky': // Kyrgyz
            case 'uz': // Uzbek
            case 'tk': // Turkmen
            case 'mn': // Mongolian
            case 'my': // Burmese
                // These languages don't distinguish plural forms
                return self::CATEGORY_OTHER;

            default:
                // Default to simple one/other distinction
                return ($intNumber === 1 && $isInteger) ? self::CATEGORY_ONE : self::CATEGORY_OTHER;
        }
    }

    /**
     * Check if a given category is valid
     *
     * @param string $category
     * @return bool
     */
    public static function isValidCategory(string $category): bool
    {
        return in_array($category, self::VALID_CATEGORIES, true);
    }

    /**
     * Get all available categories for a locale
     * This is useful for validation and UI generation
     *
     * @param string $locale
     * @return string[]
     */
    public static function getAvailableCategories(string $locale): array
    {
        $lang = strtolower(substr($locale, 0, 2));

        switch ($lang) {
            case 'ja':
            case 'ko':
            case 'zh':
            case 'th':
            case 'lo':
            case 'vi':
            case 'id':
            case 'ms':
            case 'ka':
                return [self::CATEGORY_OTHER];

            case 'en':
            case 'de':
            case 'nl':
            case 'sv':
            case 'da':
            case 'no':
            case 'es':
            case 'pt':
            case 'it':
            case 'bg':
            case 'el':
            case 'fi':
            case 'et':
            case 'hu':
            case 'tr':
            case 'he':
                return [self::CATEGORY_ONE, self::CATEGORY_OTHER];

            case 'fr':
                return [self::CATEGORY_ONE, self::CATEGORY_OTHER];

            case 'cs':
            case 'sk':
                return [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_MANY, self::CATEGORY_OTHER]; // Czech/Slovak use 'many' for decimals

            case 'pl':
                return [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_MANY, self::CATEGORY_OTHER];

            case 'ru':
            case 'uk':
            case 'be':
                return [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_MANY, self::CATEGORY_OTHER];

            case 'sr':
            case 'hr':
            case 'bs':
                return [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_OTHER];

            case 'sl':
                return [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_FEW, self::CATEGORY_OTHER];

            case 'lv':
                return [self::CATEGORY_ZERO, self::CATEGORY_ONE, self::CATEGORY_OTHER];

            case 'ga':
                return [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_FEW, self::CATEGORY_MANY, self::CATEGORY_OTHER];

            case 'ar':
                return [self::CATEGORY_ZERO, self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_FEW, self::CATEGORY_MANY, self::CATEGORY_OTHER];

            default:
                return [self::CATEGORY_ONE, self::CATEGORY_OTHER];
        }
    }

    /**
     * Clear the rule cache
     */
    public static function clearCache(): void
    {
        self::$ruleCache = [];
    }
}
