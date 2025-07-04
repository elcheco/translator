<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator\NeonDictionary;

use Nette\Neon\Neon;
use ElCheco\Translator\Dictionary;

final class NeonDictionary extends Dictionary
{

	public function __construct(
        private string $filename,
        private string $cacheFilename,
        private ?string $fallbackFilename = null
    ) {
		if (!\is_file($filename)) {
			throw NeonDictionaryException::fileNotFound($filename);
		}

        if ($fallbackFilename && !\is_file($fallbackFilename)) {
            throw NeonDictionaryException::fileNotFound($fallbackFilename);
        }
	}

	protected function lazyLoad(): void
	{
		if (!$this->isReady()) {

			if (\is_file($this->cacheFilename)) {

				// load cache
				$this->setMessages(require $this->cacheFilename);

			} else {

                // load translations from neon file
                if ($this->fallbackFilename) {
                    $fallbackDecoded = Neon::decode(\file_get_contents($this->fallbackFilename));
                    $fallbackTranslations = \is_array($fallbackDecoded) ? $fallbackDecoded : [];
                }

			    // load translations from neon file
				$decoded = Neon::decode(\file_get_contents($this->filename));
				$translations = \is_array($decoded) ? $decoded: [];

                if ($this->fallbackFilename) {
                    $translations = \array_merge($fallbackTranslations, $translations);
                }

				$translations = $this->parse($translations);

				// save cache
				$content = '<?php ' . PHP_EOL . 'return ' . \var_export($translations, true) . ';' . PHP_EOL;
				\file_put_contents("file://$this->cacheFilename", $content);

				$this->setMessages($translations);
			}
		}
	}

	protected function parse(array $translations): array
    {
        foreach ($translations as &$translation) {
            if (\is_array($translation)) {
                foreach ($translation as $key => $value) {
                    if (\is_string($key)) {
                        if (\preg_match('/^(\d+)-(\d+)$/iu', $key, $matches)) {
                            if (\count($matches) === 3) {
                                for ($i = $matches[1]; $i <= $matches[2]; $i++) {
                                    $translation[$i] = $value;
                                }
                                unset($translation[$key]);
                            }
                        }
                    }
                }
            }
        }

        return $translations;
    }

}
