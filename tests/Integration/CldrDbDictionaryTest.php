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

        // Skip if sqlite3 extension is not available
        if (!extension_loaded('sqlite3')) {
            $this->markTestSkipped('The SQLite3 extension is not available.');
        }

        // Create in-memory SQLite database for testing
        $this->connection = new Connection([
            'driver' => 'sqlite3',
            'database' => ':memory:',
        ]);

        // Set up a database schema
        $this->setupDatabase();

        // Insert test data
        $this->insertTestData();
    }

    /**
     * Test that usage tracking works correctly when enabled
     */
    public function testUsageTracking(): void
    {
        // Create CldrDbDictionaryFactory with tracking enabled
        $factory = new CldrDbDictionaryFactory($this->connection, 'TestModule', true);

        // Create CldrTranslator
        $translator = new CldrTranslator($factory);
        $translator->setLocale('cs_CZ');

        // Get the initial usage count
        $initialCount = $this->getUsageCount('room_count');
        $this->assertEquals(0, $initialCount, 'Initial usage count should be 0');

        // Use the translation multiple times
        $translator->translate('room_count', 1);
        $translator->translate('room_count', 2);
        $translator->translate('room_count', 3);

        // Get the dictionary to force it to be created
        $reflection = new \ReflectionClass($translator);
        $getDictionaryMethod = $reflection->getMethod('getDictionary');
        $getDictionaryMethod->setAccessible(true);
        $dictionary = $getDictionaryMethod->invoke($translator);

        // Get the underlying DbDictionary
        $reflection = new \ReflectionClass($dictionary);
        $dbDictionaryProperty = $reflection->getProperty('dbDictionary');
        $dbDictionaryProperty->setAccessible(true);
        $dbDictionary = $dbDictionaryProperty->getValue($dictionary);

        // Explicitly unset the dictionary to trigger the destructor
        // This simulates what happens when the application shuts down
        unset($dbDictionary);
        unset($dictionary);
        unset($translator);

        // Force garbage collection to ensure destructor is called
        gc_collect_cycles();

        // Check that usage count has been updated automatically via the destructor
        $newCount = $this->getUsageCount('room_count');
        $this->assertEquals(3, $newCount, 'Usage count should be automatically updated to 3 via destructor');
    }

    /**
     * Test that usage tracking is disabled when configured
     */
    public function testUsageTrackingDisabled(): void
    {
        // Create CldrDbDictionaryFactory with tracking disabled
        $factory = new CldrDbDictionaryFactory($this->connection, 'TestModule', false);

        // Create CldrTranslator
        $translator = new CldrTranslator($factory);
        $translator->setLocale('cs_CZ');

        // Get the initial usage count
        $initialCount = $this->getUsageCount('room_count');

        // Use the translation multiple times
        $translator->translate('room_count', 1);
        $translator->translate('room_count', 2);

        // Explicitly unset the dictionary to trigger the destructor
        unset($translator);
        gc_collect_cycles();

        // Check that usage count has not been updated
        $newCount = $this->getUsageCount('room_count');
        $this->assertEquals($initialCount, $newCount, 'Usage count should not be updated when tracking is disabled');
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

    /**
     * Get the usage count for a translation key
     *
     * @param string $key
     * @return int
     */
    private function getUsageCount(string $key): int
    {
        $result = $this->connection->query('
            SELECT [usage_count]
            FROM [translation_keys]
            WHERE [key] = %s
        ', $key)->fetch();

        return $result ? (int) $result['usage_count'] : 0;
    }
}
