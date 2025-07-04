<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;

class Translation
{
    private int $max;
    private static ?PluralRulesInterface $customPluralRules = null;

    /**
     * @param array<int|string, string> $translation
     */
    public function __construct(
        private array $translation,
        private string $locale = 'en'
    ) {
        $this->max = max(array_keys($translation));
    }

    /**
     * Set a custom plural rules implementation
     */
    public static function setCustomPluralRules(PluralRulesInterface $rules): void
    {
        self::$customPluralRules = $rules;
    }

    /**
     * Gets the appropriate translation form based on count
     *
     * @param int|null $count The count for plural form selection, or null for default form
     * @return string The selected translation
     */
    public function get(?int $count): string
    {
        if ($count === null) {
            // For null count, use the highest form but leave the %s placeholder intact
            return str_replace('%count%', '%s', $this->translation[$this->max] ?? '');
        }

        // Use custom plural rules if set, otherwise use default
        if (self::$customPluralRules !== null) {
            $normalizedCount = self::$customPluralRules->getNormalizedCount($this->locale, $count);
        } else {
            $normalizedCount = PluralRules::getNormalizedCount($this->locale, $count);
        }

        $translationKey = isset($this->translation[$normalizedCount]) ? $normalizedCount : $this->max;

        // Note: We don't replace %s with the count here anymore
        // This will be handled by the Translator class using vsprintf
        return $this->translation[$translationKey];
    }
}
