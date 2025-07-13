<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

use Dibi\Connection;
use ElCheco\Translator\DbDictionary\DbDictionary;
use ElCheco\Translator\DictionaryInterface;
use ElCheco\Translator\TranslatorException;

/**
 * Enhanced DbDictionary wrapper that supports CLDR format
 *
 * This class uses composition to wrap a DbDictionary instance
 * and add CLDR support.
 */
final class CldrDbDictionary implements DictionaryInterface
{
    /**
     * The wrapped DbDictionary instance
     */
    private DbDictionary $dbDictionary;

    /**
     * Metadata about translation formats
     * @var array<string, array{format: string, pattern?: string}>
     */
    private array $formatMetadata = [];

    /**
     * Flag to track if metadata has been loaded
     */
    private bool $metadataLoaded = false;

    /**
     * Constructor
     */
    public function __construct(DbDictionary $dbDictionary)
    {
        $this->dbDictionary = $dbDictionary;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $message): string|array
    {
        $this->loadMetadataIfNeeded();
        return $this->dbDictionary->get($message);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $message): bool
    {
        return $this->dbDictionary->has($message);
    }

    /**
     * Get format metadata for a key
     *
     * @param string $key
     * @return array{format: string, pattern?: string}|null
     */
    public function getFormatMetadata(string $key): ?array
    {
        $this->loadMetadataIfNeeded();
        return $this->formatMetadata[$key] ?? null;
    }

    /**
     * Load metadata if it hasn't been loaded yet
     */
    private function loadMetadataIfNeeded(): void
    {
        if ($this->metadataLoaded) {
            return;
        }

        // Force the DbDictionary to load its data
        if ($this->dbDictionary->has('__dummy__')) {
            // This is just to trigger the lazyLoad method in DbDictionary
        }

        // Now extract and process the translations to build metadata
        $reflection = new \ReflectionClass(DbDictionary::class);

        // Get the messages from DbDictionary
        $messagesProperty = $reflection->getParentClass()->getProperty('messages');
        $messagesProperty->setAccessible(true);
        $messages = $messagesProperty->getValue($this->dbDictionary);

        // Process messages to build metadata
        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                $cldrInfo = $this->detectCldrFormat($value);

                if ($cldrInfo['isCldr']) {
                    $this->formatMetadata[$key] = [
                        'format' => 'icu',
                        'pattern' => $cldrInfo['pattern'],
                        'categories' => array_keys($cldrInfo['forms'])
                    ];
                } else {
                    $this->formatMetadata[$key] = ['format' => 'sprintf'];
                }
            } else {
                $this->formatMetadata[$key] = ['format' => 'sprintf'];
            }
        }

        $this->metadataLoaded = true;
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
}
