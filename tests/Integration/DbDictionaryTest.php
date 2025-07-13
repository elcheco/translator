<?php

declare(strict_types=1);

namespace ElCheco\Translator\Tests\Integration;

use Dibi\Connection;
use ElCheco\Translator\DbDictionary\DbDictionaryFactory;
use ElCheco\Translator\TranslatorException;
use Mockery;
use PHPUnit\Framework\TestCase;

class DbDictionaryTest extends TestCase
{
    private Connection $connection;
    private string $testDbFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test SQLite database
        $this->testDbFile = sys_get_temp_dir() . '/translator_test_' . uniqid() . '.db';
        $this->connection = new Connection([
            'driver' => 'sqlite',
            'database' => $this->testDbFile,
        ]);

        // Create database structure
        $this->createDatabaseStructure();

        // Insert test data
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->connection->disconnect();
        if (file_exists($this->testDbFile)) {
            unlink($this->testDbFile);
        }
        Mockery::close();
    }

    /**
     * Test loading translations from database
     */
    public function testLoadTranslations(): void
    {
        $factory = new DbDictionaryFactory($this->connection, 'TestModule');
        $dictionary = $factory->create('en_US');

        // Test simple translation
        $this->assertTrue($dictionary->has('welcome'));
        $this->assertEquals('Welcome to our website', $dictionary->get('welcome'));

        // Test translation with parameter
        $this->assertTrue($dictionary->has('hello'));
        $this->assertEquals('Hello %s', $dictionary->get('hello'));

        // Test plural translation
        $this->assertTrue($dictionary->has('messages_count'));
        $plural = $dictionary->get('messages_count');
        $this->assertIsArray($plural);
        $this->assertEquals('You have %s message', $plural[1]);
        $this->assertEquals('You have %s messages', $plural[2]);
    }

    /**
     * Test fallback locale
     */
    public function testFallbackLocale(): void
    {
        $factory = new DbDictionaryFactory($this->connection, 'TestModule');
        $dictionary = $factory->create('de_DE', 'en_US');

        // German translation exists
        $this->assertEquals('Willkommen', $dictionary->get('welcome'));

        // German translation doesn't exist, fallback to English
        $this->assertEquals('Goodbye', $dictionary->get('goodbye'));
    }

    /**
     * Test usage tracking
     */
    public function testUsageTracking(): void
    {
        $factory = new DbDictionaryFactory($this->connection, 'TestModule', true);
        $dictionary = $factory->create('en_US');

        // Track usage
        $dictionary->get('welcome');
        $dictionary->get('welcome');
        $dictionary->get('hello');

        // Save usage stats
        $dictionary->saveUsageStats();

        // Check usage counts
        $result = $this->connection->query('
            SELECT [key], [usage_count] 
            FROM [translation_keys] 
            WHERE [key] IN (%s, %s)
            ORDER BY [key]
        ', 'hello', 'welcome')->fetchAll();

        $this->assertEquals('hello', $result[0]['key']);
        $this->assertEquals(1, $result[0]['usage_count']);
        $this->assertEquals('welcome', $result[1]['key']);
        $this->assertEquals(2, $result[1]['usage_count']);
    }

    /**
     * Test CLDR format in database
     */
    public function testCldrFormatInDatabase(): void
    {
        // Insert CLDR translation
        $this->connection->query('
            INSERT INTO [translation_keys] ([module_id], [key], [type], [format_type])
            VALUES (1, %s, %s, %s)
        ', 'items_count', 'plural', 'icu');

        $keyId = $this->connection->getInsertId();

        $this->connection->query('
            INSERT INTO [translations] ([key_id], [locale], [plural_values], [is_translated])
            VALUES (%i, %s, %s, 1)
        ', $keyId, 'en_US', json_encode([
            'zero' => 'No items',
            'one' => 'One item',
            'other' => '{count} items'
        ]));

        $factory = new DbDictionaryFactory($this->connection, 'TestModule');
        $dictionary = $factory->create('en_US');

        $cldrTranslation = $dictionary->get('items_count');
        $this->assertIsArray($cldrTranslation);
        $this->assertEquals('No items', $cldrTranslation['zero']);
        $this->assertEquals('One item', $cldrTranslation['one']);
        $this->assertEquals('{count} items', $cldrTranslation['other']);
    }

    /**
     * Test non-existent module
     */
    public function testNonExistentModule(): void
    {
        $this->expectException(TranslatorException::class);
        $this->expectExceptionMessage("Translation module 'NonExistent' not found.");

        $factory = new DbDictionaryFactory($this->connection, 'NonExistent');
        $dictionary = $factory->create('en_US');
        $dictionary->get('test'); // Trigger lazy load
    }

    /**
     * Test transaction rollback on error
     */
    public function testTransactionRollback(): void
    {
        $factory = new DbDictionaryFactory($this->connection, 'TestModule', true);
        $dictionary = $factory->create('en_US');

        // Track some usage
        $dictionary->get('welcome');

        // Create a new connection that we can break
        $brokenConnection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:', // This will be different from the main connection
        ]);

        // Close the broken connection to simulate database error
        $brokenConnection->disconnect();

        // Use reflection to inject the broken connection
        $reflection = new \ReflectionClass($dictionary);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);
        $property->setValue($dictionary, $brokenConnection);

        // Should handle the error gracefully
        $this->expectException(\Exception::class);
        $dictionary->saveUsageStats();
    }

    /**
     * Test loading translations with different types
     */
    public function testDifferentTranslationTypes(): void
    {
        // Add HTML type translation
        $this->connection->query('
            INSERT INTO [translation_keys] ([module_id], [key], [type])
            VALUES (1, %s, %s)
        ', 'html_content', 'html');

        $keyId = $this->connection->getInsertId();

        $this->connection->query('
            INSERT INTO [translations] ([key_id], [locale], [value], [is_translated])
            VALUES (%i, %s, %s, 1)
        ', $keyId, 'en_US', '<strong>Bold text</strong>');

        $factory = new DbDictionaryFactory($this->connection, 'TestModule');
        $dictionary = $factory->create('en_US');

        $this->assertEquals('<strong>Bold text</strong>', $dictionary->get('html_content'));
    }

    // Helper methods

    private function createDatabaseStructure(): void
    {
        // Create modules table
        $this->connection->query('
            CREATE TABLE translation_modules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255),
                is_active INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create keys table
        $this->connection->query('
            CREATE TABLE translation_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module_id INTEGER NOT NULL,
                key VARCHAR(255) NOT NULL,
                type VARCHAR(10) DEFAULT "text",
                format_type VARCHAR(10) DEFAULT "sprintf",
                description TEXT,
                usage_count INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (module_id) REFERENCES translation_modules(id)
            )
        ');

        // Create translations table
        $this->connection->query('
            CREATE TABLE translations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_id INTEGER NOT NULL,
                locale VARCHAR(10) NOT NULL,
                value TEXT,
                plural_values TEXT,
                is_translated INTEGER DEFAULT 0,
                is_approved INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (key_id) REFERENCES translation_keys(id)
            )
        ');
    }

    private function insertTestData(): void
    {
        // Insert test module
        $this->connection->query('
            INSERT INTO translation_modules (name, description)
            VALUES (%s, %s)
        ', 'TestModule', 'Test module for unit tests');

        $moduleId = 1;

        // Insert translation keys
        $keys = [
            ['key' => 'welcome', 'type' => 'text'],
            ['key' => 'hello', 'type' => 'text'],
            ['key' => 'goodbye', 'type' => 'text'],
            ['key' => 'messages_count', 'type' => 'plural'],
        ];

        foreach ($keys as $keyData) {
            $this->connection->query('
                INSERT INTO translation_keys (module_id, key, type)
                VALUES (%i, %s, %s)
            ', $moduleId, $keyData['key'], $keyData['type']);
        }

        // Insert translations
        $translations = [
            // English
            ['key_id' => 1, 'locale' => 'en_US', 'value' => 'Welcome to our website'],
            ['key_id' => 2, 'locale' => 'en_US', 'value' => 'Hello %s'],
            ['key_id' => 3, 'locale' => 'en_US', 'value' => 'Goodbye'],
            ['key_id' => 4, 'locale' => 'en_US', 'plural_values' => json_encode([
                1 => 'You have %s message',
                2 => 'You have %s messages'
            ])],

            // German (partial)
            ['key_id' => 1, 'locale' => 'de_DE', 'value' => 'Willkommen'],
            ['key_id' => 2, 'locale' => 'de_DE', 'value' => 'Hallo %s'],
        ];

        foreach ($translations as $trans) {
            $this->connection->query('
                INSERT INTO translations (key_id, locale, value, plural_values, is_translated)
                VALUES (%i, %s, %s, %s, 1)
            ',
                $trans['key_id'],
                $trans['locale'],
                $trans['value'] ?? null,
                $trans['plural_values'] ?? null
            );
        }
    }
}
