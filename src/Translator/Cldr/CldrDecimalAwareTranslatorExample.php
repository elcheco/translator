<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

/**
 * Example usage in an enhanced translator
 */
class CldrDecimalAwareTranslatorExample extends CldrTranslator
{
    use CldrDecimalSupport;

    /**
     * {@inheritdoc}
     */
    protected function formatIcuMessage(string $pattern, $count, array $parameters): string
    {
        // Enhance a pattern for better decimal support
        $enhancedPattern = $this->enhanceIcuPattern($pattern, $this->getLocale());

        // Use the enhanced decimal-aware formatter
        return $this->formatIcuMessageWithDecimals(
            $enhancedPattern,
            $count,
            $parameters,
            $this->getLocale()
        );
    }

    /**
     * Format a number for display in the current locale
     *
     * @param float|int $number
     * @param int|null $decimals
     * @return string
     */
    public function formatNumber($number, ?int $decimals = null): string
    {
        return $this->formatLocalizedNumber($number, $this->getLocale(), $decimals);
    }
}
