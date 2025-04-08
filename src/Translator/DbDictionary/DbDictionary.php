<?php

declare(strict_types=1);

namespace ElCheco\Translator\DbDictionary;

use Dibi\Connection;
use ElCheco\Translator\Dictionary;
use ElCheco\Translator\TranslatorException;

final class DbDictionary extends Dictionary
{
    private Connection $connection;
    private string $locale;
    private ?string $fallbackLocale;
    private string $module;
    private bool $trackUsage;

    /** @var array<string, int> */
    private array $usedKeys = [];

    public function __construct(
        Connection $connection,
        string $locale,
        string $module,
        ?string $fallbackLocale = null,
        bool $trackUsage = true
    ) {
        $this->connection = $connection;
        $this->locale = $locale;
        $this->module = $module;
        $this->fallbackLocale = $fallbackLocale;
        $this->trackUsage = $trackUsage;
    }

    /**
     * {@inheritdoc}
     */
    protected function lazyLoad(): void
    {
        if (!$this->isReady()) {
            $moduleId = $this->getModuleId($this->module);
            if (!$moduleId) {
                throw new TranslatorException(sprintf("Translation module '%s' not found.", $this->module));
            }

            $translations = $this->loadTranslations($moduleId, $this->locale);

            // If fallback locale is set and different from current locale, load fallback translations
            if ($this->fallbackLocale !== null && $this->fallbackLocale !== $this->locale) {
                $fallbackTranslations = $this->loadTranslations($moduleId, $this->fallbackLocale);
                // Merge fallback translations, but let current locale translations take precedence
                $translations = array_merge($fallbackTranslations, $translations);
            }

            $this->setMessages($translations);
        }
    }

    /**
     * Track the usage of a translation key.
     *
     * @param string $key
     */
    public function trackKey(string $key): void
    {
        if ($this->trackUsage) {
            if (!isset($this->usedKeys[$key])) {
                $this->usedKeys[$key] = 0;
            }
            $this->usedKeys[$key]++;
        }
    }

    /**
     * Save usage statistics for tracked keys.
     */
    public function saveUsageStats(): void
    {
        if (!$this->trackUsage || empty($this->usedKeys)) {
            return;
        }

        $moduleId = $this->getModuleId($this->module);
        if (!$moduleId) {
            return;
        }

        // Begin transaction
        $this->connection->begin();

        try {
            foreach ($this->usedKeys as $key => $count) {
                $this->connection->query('
                    UPDATE [translation_keys]
                    SET [usage_count] = [usage_count] + %i
                    WHERE [module_id] = %i AND [key] = %s
                ', $count, $moduleId, $key);
            }

            // Commit transaction
            $this->connection->commit();

            // Reset used keys
            $this->usedKeys = [];
        } catch (\Exception $e) {
            // Rollback on error
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Get module ID by name.
     *
     * @param string $moduleName
     * @return int|null
     */
    private function getModuleId(string $moduleName): ?int
    {
        $result = $this->connection->query('
            SELECT [id]
            FROM [translation_modules]
            WHERE [name] = %s AND [is_active] = 1
            LIMIT 1
        ', $moduleName)->fetch();

        return $result ? (int) $result['id'] : null;
    }

    /**
     * Load translations for a module and locale.
     *
     * @param int $moduleId
     * @param string $locale
     * @return array<string, string|array<int|string, string>>
     */
    private function loadTranslations(int $moduleId, string $locale): array
    {
        $translations = [];

        $rows = $this->connection->query('
            SELECT k.key, k.type, t.value, t.plural_values
            FROM [translation_keys] k
            LEFT JOIN [translations] t ON k.id = t.key_id AND t.locale = %s
            WHERE k.module_id = %i
        ', $locale, $moduleId)->fetchAll();

        foreach ($rows as $row) {
            $key = $row['key'];

            // Handle different translation types
            if ($row['type'] === 'plural' && $row['plural_values']) {
                // Plural values are stored as JSON, decode them
                $pluralValues = json_decode($row['plural_values'], true);
                if ($pluralValues) {
                    $translations[$key] = $pluralValues;
                }
            } else {
                // Text and HTML types or when plural values are not available
                if ($row['value'] !== null && $row['value'] !== '') {
                    $translations[$key] = $row['value'];
                }
            }
        }

        return $translations;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $message): string|array
    {
        $result = parent::get($message);
        $this->trackKey($message);
        return $result;
    }
}
