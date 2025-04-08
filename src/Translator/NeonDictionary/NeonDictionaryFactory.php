<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://koula.eu
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

    /**
     * @var bool
     */
    private $autoRefreshEnabled;


    public function __construct(
        string $directory,
        string $cacheDir,
        bool $autoRefreshEnabled = true,
        int $cacheDirMode = 0775
    ) {
        if (!\is_dir($directory)) {
            throw NeonDictionaryException::translationDirNotFound($directory);
        }

        $this->directory = $directory;

        if (!\is_dir($cacheDir) && @!\mkdir($cacheDir, $cacheDirMode, true) || !\is_writable($cacheDir)) {
            throw NeonDictionaryException::cacheDirIsNotWritable($cacheDir);
        }

        $this->cacheDir = $cacheDir;
        $this->autoRefreshEnabled = $autoRefreshEnabled;
    }

    /**
     * Set whether auto-refresh should be enabled
     *
     * @param bool $enabled
     * @return self
     */
    public function setAutoRefreshEnabled(bool $enabled): self
    {
        $this->autoRefreshEnabled = $enabled;
        return $this;
    }

    /**
     * Get whether auto-refresh is enabled
     *
     * @return bool
     */
    public function isAutoRefreshEnabled(): bool
    {
        return $this->autoRefreshEnabled;
    }

    public function create(string $locale, ?string $fallbackLocale = null): DictionaryInterface
    {
        $sourceFile = "$this->directory/$locale.neon";
        $cacheFile = "$this->cacheDir/$locale.php";

        if ($fallbackLocale) {
            $fallbackSourceFile = "$this->directory/$fallbackLocale.neon";
        } else {
            $fallbackSourceFile = null;
        }

        $dictionary = new NeonDictionary($sourceFile, $cacheFile, $fallbackSourceFile);

        // Set auto-refresh option
        if (method_exists($dictionary, 'setAutoRefreshEnabled')) {
            $dictionary->setAutoRefreshEnabled($this->autoRefreshEnabled);
        }

        return $dictionary;
    }
}
