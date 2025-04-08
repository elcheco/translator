<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator\NeonDictionary;


use ElCheco\Translator\TranslatorException;

final class NeonDictionaryException extends TranslatorException
{

	public static function cacheDirIsNotWritable(string $cacheDir)
	{
		return new static(sprintf("Cache directory %s is not writable.", $cacheDir));
	}


	public static function fileNotFound(string $filename)
	{
		return new static(sprintf("Translation file %s not found.", $filename));
	}


	public static function translationDirNotFound(string $dir)
	{
		return new static(sprintf("Translation directory %s not found.", $dir));
	}

}
