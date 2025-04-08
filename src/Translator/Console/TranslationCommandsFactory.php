<?php

declare(strict_types=1);

namespace ElCheco\Translator\Console;

use Dibi\Connection;
use Symfony\Component\Console\Command\Command;

class TranslationCommandsFactory
{
    /**
     * Creates an import command
     *
     * @param Connection $connection
     * @return Command
     */
    public static function createImportCommand(Connection $connection): Command
    {
        return new ImportNeonTranslationsCommand($connection);
    }

    /**
     * Creates an export command
     *
     * @param Connection $connection
     * @return Command
     */
    public static function createExportCommand(Connection $connection): Command
    {
        return new ExportNeonTranslationsCommand($connection);
    }
}
