<?php

declare(strict_types=1);

namespace ElCheco\Translator\Tests\Integration;

use Dibi\Connection;
use ElCheco\Translator\Cldr\CldrDbDictionaryFactory;
use ElCheco\Translator\Cldr\CldrTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Test for CldrDbDictionary with CldrTranslator
 */
class CldrDbDictionaryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if intl extension is not available
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The Intl extension is not available.');
        }

        // Create in-memory SQLite database for testing
        $this->connection = new Connection([
            'driver' => 'sqlite3',
            'database' => ':memory:',
        ]);

        // Set up database schema
        $this->setupDatabase();

        // Insert test data
        $this->insertTestData();
    }

    /**
     * Test Czech plurals with CldrDbDictionary and CldrTranslator
     */
    public function testCzechPlurals(): void
    {
        // Create CldrDbDictionaryFactory
        $factory = new CldrDbDictionaryFactory($this->connection, 'TestModule', false);

        // Create CldrTranslator
        $translator = new CldrTranslator($factory);
        $translator->setLocale('cs_CZ');

        // Test one (1)
        $this->assertEquals('1 pokoj', $translator->translate('room_count', 1));

        // Test few (2-4)
        $this->assertEquals('2 pokoje', $translator->translate('room_count', 2));
        $this->assertEquals('3 pokoje', $translator->translate('room_count', 3));
        $this->assertEquals('4 pokoje', $translator->translate('room_count', 4));

        // Test other (0, 5+)
        $this->assertEquals('0 pokojů', $translator->translate('room_count', 0));
        $this->assertEquals('5 pokojů', $translator->translate('room_count', 5));
        $this->assertEquals('10 pokojů', $translator->translate('room_count', 10));

        // Test many (decimals)
        $this->assertEquals('1,5 pokoje', $translator->translate('room_count', 1.5));
    }

    /**
     * Set up database schema
     */
    private function setupDatabase(): void
    {
        // Create modules table
        $this->connection->query('
            CREATE TABLE [translation_modules] (
                [id] INTEGER PRIMARY KEY AUTOINCREMENT,
                [name] TEXT NOT NULL,
                [is_active] INTEGER NOT NULL DEFAULT 1
            )
        ');

        // Create keys table
        $this->connection->query('
            CREATE TABLE [translation_keys] (
                [id] INTEGER PRIMARY KEY AUTOINCREMENT,
                [module_id] INTEGER NOT NULL,
                [key] TEXT NOT NULL,
                [type] TEXT NOT NULL DEFAULT "text",
                [usage_count] INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY ([module_id]) REFERENCES [translation_modules] ([id])
            )
        ');

        // Create translations table
        $this->connection->query('
            CREATE TABLE [translations] (
                [id] INTEGER PRIMARY KEY AUTOINCREMENT,
                [key_id] INTEGER NOT NULL,
                [locale] TEXT NOT NULL,
                [value] TEXT,
                [plural_values] TEXT,
                FOREIGN KEY ([key_id]) REFERENCES [translation_keys] ([id])
            )
        ');
    }

    /**
     * Insert test data
     */
    private function insertTestData(): void
    {
        // Insert module
        $this->connection->query('
            INSERT INTO [translation_modules] ([name], [is_active])
            VALUES (%s, %i)
        ', 'TestModule', 1);

        $moduleId = $this->connection->getInsertId();

        // Insert room_count key
        $this->connection->query('
            INSERT INTO [translation_keys] ([module_id], [key], [type])
            VALUES (%i, %s, %s)
        ', $moduleId, 'room_count', 'plural');

        $keyId = $this->connection->getInsertId();

        // Insert Czech translation with CLDR plural forms
        $pluralValues = json_encode([
            'one' => '{count} pokoj',
            'few' => '{count} pokoje',
            'many' => '{count, number} pokoje',
            'other' => '{count} pokojů'
        ]);

        $this->connection->query('
            INSERT INTO [translations] ([key_id], [locale], [value], [plural_values])
            VALUES (%i, %s, %s, %s)
        ', $keyId, 'cs_CZ', null, $pluralValues);
    }
}
