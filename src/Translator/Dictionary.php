<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://elcheco.it
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;


abstract class Dictionary implements DictionaryInterface
{

	/**
	 * @var array
	 */
	private $messages;


	public function has(string $message): bool
	{
		$this->lazyLoad();

		return array_key_exists($message, $this->messages);
	}


	public function get(string $message)
	{
		$this->lazyLoad();

		return $this->messages[$message];
	}


	abstract protected function lazyLoad();


	protected function isReady(): bool
	{
		return is_array($this->messages);
	}


	protected function setMessages(array $messages): DictionaryInterface
	{
		$this->messages = $messages;

		return $this;
	}

}
