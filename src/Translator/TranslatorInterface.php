<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;

use Nette\Localization\ITranslator;

interface TranslatorInterface extends ITranslator
{
    /**
     * Sets the locale for translations
     *
     * @param string $locale
     * @return TranslatorInterface
     */
    public function setLocale(string $locale): TranslatorInterface;

    /**
     * Sets the fallback locale for translations
     *
     * @param string $locale
     * @return TranslatorInterface
     */
    public function setFallbackLocale(string $locale): TranslatorInterface;

    /**
     * Translates the message with optional parameters
     *
     * Method signature is compatible with Nette Latte's translation macro:
     * {_'message', param1, param2}
     *
     * @param string|object $message The message to translate
     * @param mixed ...$parameters Parameters where the first can be count for pluralization
     * @return string
     */
    public function translate($message, ...$parameters): string;
}
