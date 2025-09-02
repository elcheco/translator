<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

use ElCheco\Translator\DictionaryFactoryInterface;
use ElCheco\Translator\DictionaryInterface;
use ElCheco\Translator\NeonDictionary\NeonDictionaryException;

final class CldrNeonDictionaryFactory implements DictionaryFactoryInterface
{
    public function __construct(
        private string $directory,
        private string $cacheDir,
        private bool $autoRefreshEnabled = true,
        int $cacheDirMode = 0775
    ) {
        if (!\is_dir($directory)) {
            throw NeonDictionaryException::translationDirNotFound($directory);
        }

        if (!\is_dir($cacheDir) && @!\mkdir($cacheDir, $cacheDirMode, true) || !\is_writable($cacheDir)) {
            throw NeonDictionaryException::cacheDirIsNotWritable($cacheDir);
        }
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

        $dictionary = new CldrNeonDictionary($sourceFile, $cacheFile, $fallbackSourceFile);

        // Set auto-refresh option
        if (method_exists($dictionary, 'setAutoRefreshEnabled')) {
            $dictionary->setAutoRefreshEnabled($this->autoRefreshEnabled);
        }

        return $dictionary;
    }
}