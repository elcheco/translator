<?php

declare(strict_types=1);

namespace ElCheco\Translator\Cldr;

use Dibi\Connection;
use ElCheco\Translator\DbDictionary\DbDictionary;
use ElCheco\Translator\DbDictionary\DbDictionaryFactory;
use ElCheco\Translator\DictionaryFactoryInterface;
use ElCheco\Translator\DictionaryInterface;

/**
 * Factory for creating CldrDbDictionary instances
 */
final class CldrDbDictionaryFactory implements DictionaryFactoryInterface
{
    private Connection $connection;
    private string $module;
    private bool $trackUsage;
    private ?string $file;

    /**
     * Constructor
     */
    public function __construct(Connection $connection, string $module, bool $trackUsage = true, ?string $file = null)
    {
        $this->connection = $connection;
        $this->module = $module;
        $this->trackUsage = $trackUsage;
        $this->file = $file;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $locale, ?string $fallbackLocale = null): DictionaryInterface
    {
        // First create a regular DbDictionary
        $dbDictionary = new DbDictionary(
            $this->connection,
            $locale,
            $this->module,
            $fallbackLocale,
            $this->trackUsage,
            $this->file
        );

        // Then wrap it with CldrDbDictionary
        return new CldrDbDictionary($dbDictionary);
    }
}
