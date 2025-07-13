<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

use NumberFormatter;
use ElCheco\Translator\TranslatorException;

/**
 * Enhanced CldrTranslator with proper decimal number support
 *
 * This extension adds:
 * - Proper decimal number formatting based on locale
 * - Support for decimal plural rules (Czech 'many' category)
 * - Automatic number formatting in translations
 */
trait CldrDecimalSupport
{
    /**
     * Format a number according to locale rules
     *
     * @param float|int $number
     * @param string $locale
     * @param int|null $decimals Force specific number of decimals
     * @return string Formatted number
     */
    protected function formatLocalizedNumber($number, string $locale, ?int $decimals = null): string
    {
        if (!extension_loaded('intl')) {
            // Fallback to basic formatting
            if ($decimals !== null) {
                return number_format((float)$number, $decimals, '.', '');
            }
            return (string)$number;
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

        if ($decimals !== null) {
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        }

        return $formatter->format($number);
    }

    /**
     * Enhanced ICU message formatting with decimal support
     *
     * @param string $pattern ICU pattern
     * @param mixed $count Primary count parameter
     * @param array $parameters All parameters
     * @param string $locale Locale for formatting
     * @return string
     */
    protected function formatIcuMessageWithDecimals(
        string $pattern,
               $count,
        array $parameters,
        string $locale
    ): string {
        try {
            $formatter = \MessageFormatter::create($locale, $pattern);

            if ($formatter === null) {
                throw new TranslatorException('Failed to create MessageFormatter');
            }

            // Build parameters for ICU
            $icuParams = [];

            // Handle the count parameter
            if ($count !== null) {
                // Keep numeric values as-is for proper plural rule evaluation
                $icuParams['count'] = is_numeric($count) ? (float)$count : $count;
            }

            // Add other parameters by position
            foreach ($parameters as $index => $param) {
                if ($index > 0) {
                    $icuParams[$index - 1] = $param;
                }
            }

            // Also try to extract named parameters if provided
            if (isset($parameters[0]) && is_array($parameters[0])) {
                $icuParams = array_merge($icuParams, $parameters[0]);
            }

            $result = $formatter->format($icuParams);

            if ($result === false) {
                throw new TranslatorException($formatter->getErrorMessage());
            }

            return $result;
        } catch (\Exception $e) {
            // Log error and fall back
            if (method_exists($this, 'getLogger') && $this->getLogger()) {
                $this->getLogger()->warning('ICU formatting failed: ' . $e->getMessage());
            }

            // Fall back to simple replacement with localized number
            $formattedCount = $this->formatLocalizedNumber($count ?? 0, $locale);
            return str_replace('{count}', $formattedCount, $pattern);
        }
    }

    /**
     * Prepare ICU pattern with number formatting
     *
     * This method enhances the pattern to use locale-specific number formatting
     *
     * @param string $pattern Original pattern
     * @param string $locale Target locale
     * @return string Enhanced pattern
     */
    protected function enhanceIcuPattern(string $pattern, string $locale): string
    {
        // For Czech and Slovak, we might want to explicitly format decimals
        // This ensures proper decimal separator (, instead of .)
        if (in_array(substr($locale, 0, 2), ['cs', 'sk'])) {
            // Replace {count} with {count, number} for explicit number formatting
            $pattern = preg_replace('/\{count\}/', '{count, number}', $pattern);
            // Replace # with {count, number} as well
            $pattern = preg_replace('/(?<!\{[^}]*)\#(?![^{]*\})/', '{count, number}', $pattern);
        }

        return $pattern;
    }
}
