<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://elcheco.it
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;


interface DictionaryInterface
{

	/**
	 * @param string $message
	 * @return string|array
	 */
	public function get(string $message);


	public function has(string $message): bool;

}
