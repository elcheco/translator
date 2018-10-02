<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://elcheco.it
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;


use Nette\Localization\ITranslator;

interface TranslatorInterface extends ITranslator
{

	public function setLocale(string $locale): TranslatorInterface;

}
