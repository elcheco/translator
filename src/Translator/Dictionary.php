<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;

abstract class Dictionary implements DictionaryInterface
{
    /**
     * @var array<string, string|array<int|string, string>>
     */
    private array $messages = [];

    public function has(string $message): bool
    {
        $this->lazyLoad();
        return array_key_exists($message, $this->messages);
    }

    /**
     * @return string|array<int|string, string>
     * @throws TranslatorException If message not found
     */
    public function get(string $message): string|array
    {
        $this->lazyLoad();

        return $this->messages[$message] ?? throw new TranslatorException(
            sprintf('Translation message "%s" not found.', $message)
        );
    }

    abstract protected function lazyLoad(): void;

    protected function isReady(): bool
    {
        return !empty($this->messages);
    }

    /**
     * @param array<string, string|array<int|string, string>> $messages
     */
    protected function setMessages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }
}
