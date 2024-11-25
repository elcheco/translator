<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://elcheco.it
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;

use NumberFormatter;
use Psr\Log\LoggerInterface;

final class Translator implements TranslatorInterface
{
	private const ZERO_INDEX = -1;

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


	public function translate($message, ...$parameters): string
	{
		// avoid processing for empty values
		if ($message === null || $message === '') {
			return '';
		}

        if (!isset($parameters[0])) {
            $parameters[0] = null;
        }

		// convert to string
		if (\is_object($message) && \method_exists($message, '__toString')) {
			$message = (string) $message;
		}

		// numbers are formatted using locale settings (count parameter is used to define decimals)
		if (\is_numeric($message) && isset($parameters['count'])) {
			return $this->formatNumber($message, (int) $parameters['count']);
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
		if ($this->dictionary->has($message)) {

			$translation = $this->dictionary->get($message);

			// simple translation
			$result = $translation;

			// process plural
			if (\is_array($translation)) {

				if ((isset($parameters[0]) && $parameters[0] === null)) {
					$this->warn('Multiple plural forms are available (message: %s), but the $count is null.', $message);

					// fallback to highest
                    $parameters[0] = \max(\array_keys($translation));
				}

				$t = new Translation($translation);
				$result = $t->get($parameters[0]);

			}

			// protection against accidentally empty-string translations
			if ($result === '') {
				$result = $message;
			}
		}

		// remove count if not provided or explicitly set to null
		if ($parameters[0] === null) {
			\array_shift($parameters);
		}

		if (\count($parameters)) {

			// preserve some nette placeholders
			$template = \str_replace(['%label', '%name', '%value'], ['%%label', '%%name', '%%value'], $result);

			// apply parameters
			$result = \vsprintf($template, $parameters);
		}

		return $result;
	}


	private function formatNumber($number, int $decimals = 0): string
	{
		$formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
		$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
		$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

		return $formatter->format($number);
	}


	private function warn($message): string
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
