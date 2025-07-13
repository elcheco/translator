<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;

use NumberFormatter;
use Psr\Log\LoggerInterface;

class Translator implements TranslatorInterface
{
    /**
     * indicates debug mode
     *
     * @var bool
     */
    private $debugMode = false;

    /**
     * current locale
     *
     * @var string
     */
    private $locale = 'en_US';

    /**
     * fallback locale
     *
     * @var null|string
     */
    private $fallBackLocale;

    /**
     * @var DictionaryInterface|null
     */
    private $dictionary;

    /**
     * @var DictionaryFactoryInterface
     */
    private $dictionaryFactory;

    /**
     * @var LoggerInterface|null
     */
    private $logger;


    public function __construct(DictionaryFactoryInterface $dictionaryFactory, ?LoggerInterface $logger = null, $debugMode = false)
    {
        $this->dictionaryFactory = $dictionaryFactory;
        $this->logger = $logger;
        $this->debugMode = $debugMode;
    }

    /**
     * Get the current dictionary instance.
     *
     * @return DictionaryInterface|null
     */
    public function getDictionary(): ?DictionaryInterface
    {
        // Ensure dictionary is loaded
        if ($this->dictionary === null) {
            $this->dictionary = $this->dictionaryFactory->create($this->locale, $this->fallBackLocale);
        }

        return $this->dictionary;
    }


    public function setDebugMode(bool $debugMode)
    {
        $this->debugMode = $debugMode;
    }


    public function setLocale(string $locale): TranslatorInterface
    {
        if ($locale !== $this->locale) {
            $this->locale = $locale;
            $this->dictionary = null;
        }

        return $this;
    }

    public function setFallbackLocale(string $locale): TranslatorInterface
    {
        if ($locale !== $this->fallBackLocale) {
            $this->fallBackLocale = $locale;
        }

        return $this;
    }

    /**
     * Translates the message with optional parameters.
     *
     * Method signature is compatible with Nette Latte's translation macro:
     * {_'message', param1, param2}
     *
     * The first parameter can be:
     * - An integer for plural form selection
     * - Any other value for string replacement
     * - NULL if neither is needed
     *
     * @param string|object $message The message to translate
     * @param mixed ...$parameters Parameters where the first can be count for pluralization
     * @return string
     */
    public function translate($message, ...$parameters): string
    {
        // avoid processing for empty values
        if ($message === null || $message === '') {
            return '';
        }

        // convert to string
        if (\is_object($message) && \method_exists($message, '__toString')) {
            $message = (string) $message;
        }

        // numbers are formatted using locale settings when first parameter specifies decimals
        if (\is_numeric($message) && isset($parameters[0]) && \is_int($parameters[0])) {
            return $this->formatNumber($message, $parameters[0]);
        }

        // check message to be string
        if (!\is_string($message)) {
            return $this->warn('Message must be string, but %s given.', \gettype($message));
        }

        // create dictionary on first access
        if ($this->dictionary === null) {
            $this->dictionary = $this->dictionaryFactory->create($this->locale, $this->fallBackLocale);
        }

        // translation begins
        $result = $message;
        $countApplied = false;
        $count = isset($parameters[0]) ? $parameters[0] : null;

        if ($this->dictionary->has($message)) {
            $translation = $this->dictionary->get($message);

            // simple translation (string)
            if (\is_string($translation)) {
                $result = $translation;
            }
            // plural forms (array)
            else if (\is_array($translation)) {
                // If first parameter is numeric, use it for plural forms
                if ($count !== null && is_numeric($count)) {
                    $t = new Translation($translation, $this->locale);
                    $result = $t->get((int) $count);
                    $countApplied = true;
                }
                // If count is null, show a warning in debug mode and fall back to the highest form
                else if ($count === null) {
                    if ($this->debugMode) {
                        $this->warn('Multiple plural forms are available (message: %s), but the $count is NULL.', $message);
                    }
                    // Default to the highest form but preserve %s placeholders
                    $maxKey = \max(\array_keys($translation));
                    $result = $translation[$maxKey];
                    // Replace any numeric replacements (like %count%) with %s to preserve placeholders
                    $result = str_replace('%count%', '%s', $result);
                }
                // If count is not numeric (e.g. a string, array, etc.), just use the highest form
                else {
                    $maxKey = \max(\array_keys($translation));
                    $result = $translation[$maxKey];
                }
            }

            // protection against accidentally empty-string translations
            if ($result === '') {
                $result = $message;
            }
        }

        // Prepare parameters for string replacement
        $replacementParams = [];

        // If the first parameter was used for pluralization, include it for replacements
        // so it can still be used as a replacement in the string
        if (!empty($parameters)) {
            $replacementParams = $parameters;
        }

        // Apply parameters if there are any
        if (!empty($replacementParams)) {
            // preserve some nette placeholders
            $template = \str_replace(['%label', '%name', '%value'], ['%%label', '%%name', '%%value'], $result);

            // Replace null values with empty strings to avoid vsprintf errors
            foreach ($replacementParams as &$param) {
                if ($param === null) {
                    $param = '';
                }
            }

            // apply positional parameters
            $result = \vsprintf($template, $replacementParams);
        }

        return $result;
    }


    protected function formatNumber($number, int $decimals = 0): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $formatter->format($number);
    }


    protected function warn($message): string
    {
        // format message
        $args = \func_get_args();
        if (\count($args) > 1) {
            \array_shift($args);
            $message = \sprintf($message, ...$args);
        }

        // log to psr logger
        if ($this->logger !== null) {
            $message = 'translator: ' . $message;
            $this->logger->warning($message);
        }

        // throw exception in debug mode
        if ($this->debugMode === true) {
            throw new TranslatorException($message);
        }

        return $message;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
