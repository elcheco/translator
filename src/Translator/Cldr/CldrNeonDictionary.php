<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

use ElCheco\Translator\NeonDictionary\NeonDictionary;
use ElCheco\Translator\NeonDictionary\NeonDictionaryException;
use Nette\Neon\Neon;

/**
 * Enhanced NeonDictionary that supports both legacy and CLDR formats
 */
class CldrNeonDictionary extends NeonDictionary
{
    /**
     * Metadata about translation formats
     * @var array<string, array{format: string, pattern?: string}>
     */
    private array $formatMetadata = [];

    /**
     * {@inheritdoc}
     */
    protected function lazyLoad(): void
    {
        if (!$this->isReady()) {
            $cacheFilename = $this->getCacheFilename();

            if (\is_file($cacheFilename)) {
                // Load from cache
                $cached = require $cacheFilename;

                if (isset($cached['messages']) && isset($cached['metadata'])) {
                    $this->setMessages($cached['messages']);
                    $this->formatMetadata = $cached['metadata'];
                } else {
                    // Old cache format, regenerate
                    $this->loadFromNeon();
                }
            } else {
                $this->loadFromNeon();
            }
        }
    }

    /**
     * Load translations from NEON files
     */
    private function loadFromNeon(): void
    {
        $translations = [];
        $metadata = [];

        // Load fallback translations if available
        $fallbackFilename = $this->getFallbackFilename();
        if ($fallbackFilename && \is_file($fallbackFilename)) {
            $fallbackDecoded = Neon::decode(\file_get_contents($fallbackFilename));
            if (\is_array($fallbackDecoded)) {
                $parsed = $this->parseWithMetadata($fallbackDecoded);
                $translations = $parsed['translations'];
                $metadata = $parsed['metadata'];
            }
        }

        // Load main translations
        $filename = $this->getFilename();
        $decoded = Neon::decode(\file_get_contents($filename));
        if (\is_array($decoded)) {
            $parsed = $this->parseWithMetadata($decoded);
            $translations = \array_merge($translations, $parsed['translations']);
            $metadata = \array_merge($metadata, $parsed['metadata']);
        }

        $this->setMessages($translations);
        $this->formatMetadata = $metadata;

        // Save to cache
        $this->saveCache($translations, $metadata);
    }

    /**
     * Parse translations and extract metadata
     *
     * @param array $translations
     * @return array{translations: array, metadata: array}
     */
    private function parseWithMetadata(array $translations): array
    {
        $parsed = [];
        $metadata = [];

        foreach ($translations as $key => $value) {
            if (\is_array($value)) {
                $cldrInfo = $this->detectCldrFormat($value);

                if ($cldrInfo['isCldr']) {
                    // Store CLDR format with metadata
                    $parsed[$key] = $cldrInfo['forms'];
                    $metadata[$key] = [
                        'format' => 'icu',
                        'pattern' => $cldrInfo['pattern'],
                        'categories' => array_keys($cldrInfo['forms'])
                    ];
                } else {
                    // Legacy format - parse ranges and store
                    $parsed[$key] = $this->parseLegacyFormat($value);
                    $metadata[$key] = ['format' => 'sprintf'];
                }
            } else {
                // Simple string translation
                $parsed[$key] = $value;
                $metadata[$key] = ['format' => 'sprintf'];
            }
        }

        return [
            'translations' => $parsed,
            'metadata' => $metadata
        ];
    }

    /**
     * Detect if translation is in CLDR format
     *
     * @param array $translation
     * @return array{isCldr: bool, forms: array, pattern: string|null}
     */
    private function detectCldrFormat(array $translation): array
    {
        $cldrCategories = ['zero', 'one', 'two', 'few', 'many', 'other'];
        $keys = array_keys($translation);
        $isCldr = !empty(array_intersect($keys, $cldrCategories));

        if (!$isCldr) {
            return ['isCldr' => false, 'forms' => $translation, 'pattern' => null];
        }

        // Build ICU pattern from CLDR forms
        $pattern = $this->buildIcuPattern($translation);

        return [
            'isCldr' => true,
            'forms' => $translation,
            'pattern' => $pattern
        ];
    }

    /**
     * Parse legacy format (with range support)
     *
     * @param array $translation
     * @return array
     */
    private function parseLegacyFormat(array $translation): array
    {
        $parsed = [];

        foreach ($translation as $key => $value) {
            if (\is_string($key) && \preg_match('/^(\d+)-(\d+)$/iu', $key, $matches)) {
                // Handle ranges like "2-4"
                for ($i = (int)$matches[1]; $i <= (int)$matches[2]; $i++) {
                    $parsed[$i] = $value;
                }
            } else {
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Build ICU MessageFormat pattern from CLDR forms
     *
     * @param array<string, string> $forms
     * @return string
     */
    private function buildIcuPattern(array $forms): string
    {
        $parts = [];
        $orderedCategories = ['zero', 'one', 'two', 'few', 'many', 'other'];

        foreach ($orderedCategories as $category) {
            if (isset($forms[$category])) {
                $text = $forms[$category];

                // Convert common placeholders
                $text = str_replace(['%s', '%d', '%u', '%f'], '{count}', $text);

                // Handle positional parameters
                $text = preg_replace_callback('/%(\d+)\$[sduif]/', function($matches) {
                    $position = (int)$matches[1] - 1;
                    return $position === 0 ? '{count}' : '{' . $position . '}';
                }, $text);

                // Convert {count} to # for ICU
                $icuText = str_replace('{count}', '#', $text);

                $parts[] = $category . " {" . $icuText . "}";
            }
        }

        // Ensure 'other' exists
        if (!isset($forms['other'])) {
            $defaultText = $forms['many'] ?? $forms['few'] ?? $forms['one'] ?? '#';
            $defaultText = str_replace(['%s', '%d', '{count}'], '#', $defaultText);
            $parts[] = "other {" . $defaultText . "}";
        }

        return "{count, plural, " . implode(' ', $parts) . "}";
    }

    /**
     * Save parsed translations and metadata to cache
     *
     * @param array $translations
     * @param array $metadata
     */
    private function saveCache(array $translations, array $metadata): void
    {
        $cacheFilename = $this->getCacheFilename();
        $content = '<?php ' . PHP_EOL;
        $content .= 'return ' . \var_export([
                'messages' => $translations,
                'metadata' => $metadata,
                'version' => '2.0' // Cache version for future compatibility
            ], true) . ';' . PHP_EOL;

        \file_put_contents("file://$cacheFilename", $content);
    }

    /**
     * Get format metadata for a key
     *
     * @param string $key
     * @return array{format: string, pattern?: string}|null
     */
    public function getFormatMetadata(string $key): ?array
    {
        $this->lazyLoad();
        return $this->formatMetadata[$key] ?? null;
    }

    /**
     * Get the filename (accessing protected property)
     *
     * @return string
     */
    private function getFilename(): string
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('filename');
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Get the cache filename (accessing protected property)
     *
     * @return string
     */
    private function getCacheFilename(): string
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('cacheFilename');
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Get the fallback filename (accessing protected property)
     *
     * @return string|null
     */
    private function getFallbackFilename(): ?string
    {
        $reflection = new \ReflectionClass(parent::class);
        $property = $reflection->getProperty('fallbackFilename');
        $property->setAccessible(true);
        return $property->getValue($this);
    }
}
