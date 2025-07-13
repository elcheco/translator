<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

use ElCheco\Translator\Translator;
use ElCheco\Translator\Translation;
use ElCheco\Translator\DictionaryInterface;
use ElCheco\Translator\TranslatorException;
use MessageFormatter;

/**
 * Enhanced Translator with CLDR support
 *
 * This translator supports both legacy sprintf-style translations
 * and modern CLDR ICU MessageFormat patterns.
 */
class CldrTranslator extends Translator
{
    /**
     * CLDR support enabled flag
     */
    private bool $cldrEnabled = true;

    /**
     * Enable or disable CLDR support
     *
     * @param bool $enabled
     * @return self
     */
    public function setCldrEnabled(bool $enabled): self
    {
        $this->cldrEnabled = $enabled;
        return $this;
    }

    /**
     * Check if CLDR support is enabled
     *
     * @return bool
     */
    public function isCldrEnabled(): bool
    {
        return $this->cldrEnabled && extension_loaded('intl');
    }

    /**
     * {@inheritdoc}
     */
    public function translate($message, ...$parameters): string
    {
        // Handle empty messages
        if ($message === null || $message === '') {
            return '';
        }

        // Convert to string
        if (\is_object($message) && \method_exists($message, '__toString')) {
            $message = (string) $message;
        }

        // Numbers formatting (legacy support)
        if (\is_numeric($message) && isset($parameters[0]) && \is_int($parameters[0])) {
            return $this->formatNumber($message, $parameters[0]);
        }

        // Check message type
        if (!\is_string($message)) {
            return $this->warn('Message must be string, but %s given.', \gettype($message));
        }

        // Create dictionary on first access
        if ($this->getDictionary() === null) {
            $dictionaryFactory = $this->getDictionaryFactory();
            $dictionary = $dictionaryFactory->create($this->getLocale(), $this->getFallbackLocale());
            $this->setDictionary($dictionary);
        }

        $dictionary = $this->getDictionary();
        $result = $message;

        if ($dictionary->has($message)) {
            $translation = $dictionary->get($message);

            // Check if this is a CLDR-aware dictionary
            $metadata = null;
            if ($dictionary instanceof CldrNeonDictionary) {
                $metadata = $dictionary->getFormatMetadata($message);
            }

            // Handle different translation types
            if (\is_string($translation)) {
                // Simple string translation
                $result = $translation;
            } elseif (\is_array($translation)) {
                // Complex translation (plural or CLDR)
                $result = $this->handleComplexTranslation(
                    $translation,
                    $parameters,
                    $metadata
                );
            }

            // Protection against empty translations
            if ($result === '') {
                $result = $message;
            }
        }

        // Apply string formatting if we have parameters and it's not already handled by ICU
        if (!empty($parameters) && !$this->isIcuFormatted($result)) {
            $result = $this->applySprintfFormatting($result, $parameters);
        }

        return $result;
    }

    /**
     * Handle complex translation (plural or CLDR format)
     *
     * @param array $translation
     * @param array $parameters
     * @param array|null $metadata
     * @return string
     */
    private function handleComplexTranslation(
        array $translation,
        array $parameters,
        ?array $metadata
    ): string {
        $count = $parameters[0] ?? null;
        $isCldr = $metadata && isset($metadata['format']) && $metadata['format'] === 'icu';

        if ($isCldr && $this->isCldrEnabled()) {
            // Use CLDR translation
            $pattern = $metadata['pattern'] ?? null;
            if ($pattern) {
                return $this->formatIcuMessage($pattern, $count, $parameters);
            }
        }

        // Fall back to legacy translation handling
        if ($count !== null && is_numeric($count)) {
            $t = new Translation($translation, $this->getLocale());
            return $t->get((int) $count);
        } elseif ($count === null) {
            if ($this->isDebugMode()) {
                $this->warn('Multiple plural forms are available (message: %s), but the $count is NULL.', $translation);
            }
            $maxKey = \max(\array_keys($translation));
            return $translation[$maxKey];
        } else {
            $maxKey = \max(\array_keys($translation));
            return $translation[$maxKey];
        }
    }

    /**
     * Format message using ICU MessageFormatter
     *
     * @param string $pattern ICU pattern
     * @param mixed $count Primary count parameter
     * @param array $parameters All parameters
     * @return string
     */
    private function formatIcuMessage(string $pattern, $count, array $parameters): string
    {
        try {
            $formatter = MessageFormatter::create($this->getLocale(), $pattern);

            if ($formatter === null) {
                throw new TranslatorException('Failed to create MessageFormatter');
            }

            // Build parameters for ICU
            $icuParams = ['count' => $count ?? 0];

            // Add other parameters by position
            foreach ($parameters as $index => $param) {
                if ($index > 0) {
                    $icuParams[$index - 1] = $param;
                }
            }

            // Also try to extract named parameters if the first parameter is an array
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
            if ($this->getLogger()) {
                $this->getLogger()->warning('ICU formatting failed: ' . $e->getMessage());
            }

            // Fall back to simple replacement
            return str_replace('{count}', (string)($count ?? 0), $pattern);
        }
    }

    /**
     * Apply sprintf formatting to a string
     *
     * @param string $template
     * @param array $parameters
     * @return string
     */
    private function applySprintfFormatting(string $template, array $parameters): string
    {
        // Preserve some nette placeholders
        $template = \str_replace(['%label', '%name', '%value'], ['%%label', '%%name', '%%value'], $template);

        // Replace null values with empty strings
        foreach ($parameters as &$param) {
            if ($param === null) {
                $param = '';
            }
        }

        try {
            return \vsprintf($template, $parameters);
        } catch (\Exception $e) {
            // If vsprintf fails, return template as-is
            return $template;
        }
    }

    /**
     * Check if a string appears to be ICU formatted
     *
     * @param string $text
     * @return bool
     */
    private function isIcuFormatted(string $text): bool
    {
        // Simple check for ICU message format patterns
        return strpos($text, '{count, plural,') !== false
            || strpos($text, '{count, select,') !== false
            || preg_match('/\{[a-zA-Z_]\w*\}/', $text) === 1;
    }

    /**
     * Get locale (accessing private property)
     *
     * @return string
     */
    protected function getLocale(): string
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('locale');
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Get fallback locale (accessing private property)
     *
     * @return string|null
     */
    private function getFallbackLocale(): ?string
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('fallBackLocale');
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Get dictionary factory (accessing private property)
     *
     * @return DictionaryFactoryInterface
     */
    private function getDictionaryFactory(): DictionaryFactoryInterface
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('dictionaryFactory');
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Set dictionary (accessing private property)
     *
     * @param DictionaryInterface $dictionary
     */
    private function setDictionary(DictionaryInterface $dictionary): void
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('dictionary');
        $property->setAccessible(true);
        $property->setValue($this, $dictionary);
    }

    /**
     * Get logger (accessing private property)
     *
     * @return LoggerInterface|null
     */
    private function getLogger(): ?LoggerInterface
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Check debug mode (accessing private property)
     *
     * @return bool
     */
    private function isDebugMode(): bool
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('debugMode');
        $property->setAccessible(true);
        return $property->getValue($this);
    }
}
