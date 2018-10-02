<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://elcheco.it
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator\NeonDictionary;


use ElCheco\Translator\DictionaryFactoryInterface;
use ElCheco\Translator\DictionaryInterface;

final class NeonDictionaryFactory implements DictionaryFactoryInterface
{

	/**
	 * @var string
	 */
	private $directory;

	/**
	 * @var string
	 */
	private $cacheDir;


	public function __construct(string $directory, string $cacheDir, int $cacheDirMode = 0775)
	{
		if (!is_dir($directory)) {

			throw NeonDictionaryException::translationDirNotFound($directory);
		}

		$this->directory = $directory;

		if (!is_dir($cacheDir) && @!mkdir($cacheDir, $cacheDirMode, true) || !is_writable($cacheDir)) {

			throw NeonDictionaryException::cacheDirIsNotWritable($cacheDir);
		}

		$this->cacheDir = $cacheDir;
	}


	public function create(string $locale, ?string $fallbackLocale): DictionaryInterface
	{
		$sourceFile = "$this->directory/$locale.neon";
		$cacheFile = "$this->cacheDir/$locale.php";

		if ($fallbackLocale) {
            $fallbackSourceFile = "$this->directory/$fallbackLocale.neon";
        } else {
            $fallbackSourceFile = null;
        }

		return new NeonDictionary($sourceFile, $cacheFile, $fallbackSourceFile);
	}

}
