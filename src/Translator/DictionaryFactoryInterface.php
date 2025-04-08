<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;


interface DictionaryFactoryInterface
{

	public function create(string $locale, ?string $fallbackLocale = null): DictionaryInterface;

}
