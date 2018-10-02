<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://elcheco.it
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator\NeonDictionary;


use function is_array;
use Nette\Neon\Neon;
use ElCheco\Translator\Dictionary;

final class NeonDictionary extends Dictionary
{

	/**
	 * @var string
	 */
	private $filename;

	/**
	 * @var string
	 */
	private $cacheFilename;

    /**
     * @var string
     */
    private $fallbackFilename;


	public function __construct(string $filename, string $cacheFilename, ?string $fallbackFilename = null)
	{
		if (!is_file($filename)) {

			throw NeonDictionaryException::fileNotFound($filename);
		}

        if ($fallbackFilename && !is_file($fallbackFilename)) {

            throw NeonDictionaryException::fileNotFound($fallbackFilename);
        }

		$this->filename = $filename;
		$this->cacheFilename = $cacheFilename;

        $this->fallbackFilename = $fallbackFilename;
	}


	protected function lazyLoad()
	{
		if (!$this->isReady()) {

			if (is_file($this->cacheFilename)) {

				// load cache
				$this->setMessages(require $this->cacheFilename);

			} else {

                // load translations from neon file
                $fallbackDecoded = Neon::decode(file_get_contents($this->fallbackFilename));
                $fallbackTranslations = is_array($fallbackDecoded) ? $fallbackDecoded: [];

			    // load translations from neon file
				$decoded = Neon::decode(file_get_contents($this->filename));
				$translations = is_array($decoded) ? $decoded: [];

				$translations = \array_merge($fallbackTranslations, $translations);

				// save cache
				$content = '<?php ' . PHP_EOL . 'return ' . var_export($translations, true) . ';' . PHP_EOL;
				file_put_contents("safe://$this->cacheFilename", $content);

				$this->setMessages($translations);
			}
		}
	}

}
