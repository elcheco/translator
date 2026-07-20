<?php

declare(strict_types=1);

namespace ElCheco\Translator\DbDictionary;

use Dibi\Connection;
use ElCheco\Translator\DictionaryFactoryInterface;
use ElCheco\Translator\DictionaryInterface;

final class DbDictionaryFactory implements DictionaryFactoryInterface
{
    private Connection $connection;
    private string $module;
    private bool $trackUsage;
    private ?string $file;

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
        return new DbDictionary(
            $this->connection,
            $locale,
            $this->module,
            $fallbackLocale,
            $this->trackUsage,
            $this->file
        );
    }
}
