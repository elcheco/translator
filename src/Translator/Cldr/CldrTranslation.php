<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

use MessageFormatter;
use ElCheco\Translator\Translation;
use ElCheco\Translator\TranslatorException;

/**
 * Enhanced Translation class that supports both legacy sprintf format
 * and CLDR ICU MessageFormat patterns
 */
class CldrTranslation extends Translation
{
    /**
     * Format type constants
     */
    public const FORMAT_SPRINTF = 'sprintf';
    public const FORMAT_ICU = 'icu';

    private string $formatType;
    private ?string $icuPattern = null;
    private array $cldrForms = [];

    /**
     * @param array<int|string, string> $translation Translation forms
     * @param string $locale Locale code
     * @param string $formatType Format type (sprintf or icu)
     * @param string|null $icuPattern Pre-built ICU pattern (optional)
     */
    public function __construct(
        array $translation,
        string $locale = 'en',
        string $formatType = self::FORMAT_SPRINTF,
        ?string $icuPattern = null
    ) {
        // Check if this is CLDR format
        if ($this->isCldrFormat($translation)) {
            $this->formatType = self::FORMAT_ICU;
            $this->cldrForms = $translation;
            $this->icuPattern = $icuPattern ?? $this->buildIcuPattern($translation);

            // Convert CLDR to legacy format for parent class
            $legacyTranslation = $this->convertCldrToLegacy($translation);
            parent::__construct($legacyTranslation, $locale);
        } else {
            $this->formatType = $formatType;
            $this->icuPattern = $icuPattern;
            parent::__construct($translation, $locale);
        }
    }

    /**
     * Get translation with support for both formats
     *
     * @param int|null $count Count for pluralization
     * @param array<string, mixed> $parameters Additional parameters for ICU format
     * @return string
     */
    public function get(?int $count, array $parameters = []): string
    {
        if ($this->formatType === self::FORMAT_ICU && $this->icuPattern !== null) {
            return $this->getIcuFormatted($count, $parameters);
        }

        // Fall back to parent implementation for legacy format
        return parent::get($count);
    }

    /**
     * Get ICU formatted translation
     *
     * @param int|null $count
     * @param array<string, mixed> $parameters
     * @return string
     * @throws TranslatorException
     */
    private function getIcuFormatted(?int $count, array $parameters): string
    {
        if (!extension_loaded('intl')) {
            throw new TranslatorException('The Intl PHP extension is required for ICU message formatting');
        }

        $locale = $this->getLocale();
        $formatter = MessageFormatter::create($locale, $this->icuPattern);

        if ($formatter === null) {
            $error = intl_get_error_message();
            throw new TranslatorException("Invalid ICU message pattern: $error");
        }

        // Merge count into parameters
        $params = array_merge(['count' => $count ?? 0], $parameters);

        $result = $formatter->format($params);

        if ($result === false) {
            $error = $formatter->getErrorMessage();
            throw new TranslatorException("ICU message formatting failed: $error");
        }

        return $result;
    }

    /**
     * Check if translation array is in CLDR format
     *
     * @param array $translation
     * @return bool
     */
    private function isCldrFormat(array $translation): bool
    {
        $cldrCategories = ['zero', 'one', 'two', 'few', 'many', 'other'];
        $keys = array_keys($translation);

        // Check if any CLDR category is present
        return !empty(array_intersect($keys, $cldrCategories));
    }

    /**
     * Build ICU MessageFormat pattern from CLDR forms
     *
     * @param array<string, string> $forms CLDR category => text mapping
     * @return string ICU pattern
     */
    private function buildIcuPattern(array $forms): string
    {
        $parts = [];

        // Order matters for ICU - use this specific order
        $orderedCategories = ['zero', 'one', 'two', 'few', 'many', 'other'];

        foreach ($orderedCategories as $category) {
            if (isset($forms[$category])) {
                $text = $forms[$category];

                // Convert sprintf placeholders to ICU format
                // %s -> {count}, %d -> {count}, %1$s -> {0}, etc.
                $text = $this->convertSprintfToIcu($text);

                // Special handling for explicit number in 'one' category
                if ($category === 'one' && strpos($text, '{count}') === false) {
                    // If the 'one' form doesn't contain the count placeholder,
                    // it might be something like "one item" instead of "1 item"
                    $parts[] = "one {" . $text . "}";
                } else {
                    // Replace {count} with # which represents the number in ICU
                    $text = str_replace('{count}', '#', $text);
                    $parts[] = $category . " {" . $text . "}";
                }
            }
        }

        // Ensure 'other' category exists (required by ICU)
        if (!isset($forms['other'])) {
            $otherText = $forms['many'] ?? $forms['few'] ?? $forms['one'] ?? '{count}';
            $otherText = $this->convertSprintfToIcu($otherText);
            $otherText = str_replace('{count}', '#', $otherText);
            $parts[] = "other {" . $otherText . "}";
        }

        return "{count, plural, " . implode(' ', $parts) . "}";
    }

    /**
     * Convert sprintf format specifiers to ICU format
     *
     * @param string $text
     * @return string
     */
    private function convertSprintfToIcu(string $text): string
    {
        // Basic conversions
        $conversions = [
            '%s' => '{count}',
            '%d' => '{count}',
            '%u' => '{count}',
            '%f' => '{count}',
        ];

        $text = str_replace(array_keys($conversions), array_values($conversions), $text);

        // Handle positional arguments like %1$s, %2$d
        $text = preg_replace_callback('/%(\d+)\$[sduif]/', function($matches) {
            $position = (int)$matches[1] - 1; // Convert to 0-based
            return $position === 0 ? '{count}' : '{' . $position . '}';
        }, $text);

        return $text;
    }

    /**
     * Convert CLDR format to legacy numeric keys for backward compatibility
     *
     * @param array<string, string> $cldrForms
     * @return array<int, string>
     */
    private function convertCldrToLegacy(array $cldrForms): array
    {
        $legacy = [];

        // Map CLDR categories to numeric keys
        $mapping = [
            'zero' => 0,
            'one' => 1,
            'two' => 2,
            'few' => 3,
            'many' => 5,
            'other' => 5,
        ];

        foreach ($cldrForms as $category => $text) {
            if (isset($mapping[$category])) {
                $legacy[$mapping[$category]] = $text;
            }
        }

        // Ensure we have at least the 'other' form mapped to 5
        if (empty($legacy)) {
            $legacy[5] = $cldrForms['other'] ?? end($cldrForms);
        }

        return $legacy;
    }

    /**
     * Get the locale (protected method in parent class)
     *
     * @return string
     */
    private function getLocale(): string
    {
        // Use reflection to access protected property from parent
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('locale');
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Get the format type
     *
     * @return string
     */
    public function getFormatType(): string
    {
        return $this->formatType;
    }

    /**
     * Get the ICU pattern (if available)
     *
     * @return string|null
     */
    public function getIcuPattern(): ?string
    {
        return $this->icuPattern;
    }

    /**
     * Get CLDR forms (if available)
     *
     * @return array
     */
    public function getCldrForms(): array
    {
        return $this->cldrForms;
    }

    /**
     * Create a CLDR translation from a simple pattern
     *
     * Example:
     * CldrTranslation::fromPattern(
     *     'You have {count, plural, =0 {no messages} one {one message} other {# messages}}',
     *     'en_US'
     * )
     *
     * @param string $pattern ICU MessageFormat pattern
     * @param string $locale
     * @return self
     */
    public static function fromPattern(string $pattern, string $locale = 'en'): self
    {
        // Parse the pattern to extract CLDR forms
        $forms = self::parseIcuPattern($pattern);

        return new self($forms, $locale, self::FORMAT_ICU, $pattern);
    }

    /**
     * Parse ICU pattern to extract CLDR forms
     *
     * @param string $pattern
     * @return array<string, string>
     */
    private static function parseIcuPattern(string $pattern): array
    {
        $forms = [];

        // Simple regex to extract plural forms from ICU pattern
        // This is a basic implementation and might need refinement
        if (preg_match('/{count,\s*plural,\s*(.+)}/s', $pattern, $matches)) {
            $pluralContent = $matches[1];

            // Match each plural form
            preg_match_all('/(\w+|=\d+)\s*{([^}]+)}/', $pluralContent, $formMatches, PREG_SET_ORDER);

            foreach ($formMatches as $match) {
                $category = $match[1];
                $text = $match[2];

                // Handle exact number matches like =0
                if (strpos($category, '=') === 0) {
                    $number = substr($category, 1);
                    if ($number === '0') {
                        $category = 'zero';
                    } elseif ($number === '1') {
                        $category = 'one';
                    } elseif ($number === '2') {
                        $category = 'two';
                    } else {
                        // Skip other exact matches for now
                        continue;
                    }
                }

                // Convert # back to {count} for storage
                $text = str_replace('#', '{count}', $text);

                $forms[$category] = $text;
            }
        }

        return $forms;
    }
}
