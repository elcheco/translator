<?php

declare(strict_types=1);

namespace ElCheco\Translator\Tests\Console;

use PHPUnit\Framework\TestCase;
use ElCheco\Translator\Console\ImportNeonTranslationsCommand;
use ElCheco\Translator\Console\ExportNeonTranslationsCommand;
use ElCheco\Translator\Console\ConvertToCldrCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Dibi\Connection;
use Nette\Neon\Neon;

class ConsoleCommandsTest extends TestCase
{
    private Connection $connection;
    private string $testDbFile;
    private string $translationsDir;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if sqlite3 extension is not available
        if (!extension_loaded('sqlite3')) {
            $this->markTestSkipped('The SQLite3 extension is not available.');
        }

        // Create test directories
        $this->translationsDir = sys_get_temp_dir() . '/translator_import_' . uniqid();
        $this->outputDir = sys_get_temp_dir() . '/translator_export_' . uniqid();
        mkdir($this->translationsDir, 0777, true);
        mkdir($this->outputDir, 0777, true);

        // Create test database
        $this->testDbFile = sys_get_temp_dir() . '/translator_cmd_test_' . uniqid() . '.db';
        $this->connection = new Connection([
            'driver' => 'sqlite3',
            'database' => $this->testDbFile,
        ]);

        $this->createDatabaseStructure();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->connection->disconnect();

        // Clean up
        if (file_exists($this->testDbFile)) {
            unlink($this->testDbFile);
        }

        $this->removeDirectory($this->translationsDir);
        $this->removeDirectory($this->outputDir);
    }

    /**
     * Test importing NEON translations
     */
    public function testImportNeonTranslations(): void
    {
        // Create test NEON file
        $translations = [
            'welcome' => 'Welcome to our website',
            'hello' => 'Hello %s',
            'messages_count' => [
                1 => 'You have %s message',
                2 => 'You have %s messages'
            ]
        ];

        file_put_contents(
            $this->translationsDir . '/en_US.neon',
            Neon::encode($translations, true)
        );

        // Execute import command
        $application = new Application();
        $command = new ImportNeonTranslationsCommand($this->connection);
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'directory' => $this->translationsDir,
            'module' => 'TestModule',
            '--locale' => 'en_US',
            '--mark-as-translated' => true,
        ]);

        // Assert command succeeded
        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 3', $output);

        // Verify data in database
        $module = $this->connection->query('
            SELECT * FROM translation_modules WHERE name = %s
        ', 'TestModule')->fetch();
        $this->assertNotNull($module);

        $keys = $this->connection->query('
            SELECT * FROM translation_keys WHERE module_id = %i
        ', $module['id'])->fetchAll();
        $this->assertCount(3, $keys);

        // Check plural translation
        $pluralKey = array_filter($keys, fn($k) => $k['key'] === 'messages_count');
        $pluralKey = array_values($pluralKey)[0];
        $this->assertEquals('plural', $pluralKey['type']);

        $pluralTrans = $this->connection->query('
            SELECT * FROM translations WHERE key_id = %i
        ', $pluralKey['id'])->fetch();
        $this->assertNotNull($pluralTrans['plural_values']);
        $this->assertEquals(1, $pluralTrans['is_translated']);
    }

    /**
     * Test exporting translations to NEON
     */
    public function testExportNeonTranslations(): void
    {
        // Insert test data
        $this->insertTestData();

        // Execute export command
        $application = new Application();
        $command = new ExportNeonTranslationsCommand($this->connection);
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'module' => 'TestModule',
            'locale' => 'en_US',
            '--output-dir' => $this->outputDir,
        ]);

        // Assert command succeeded
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Check exported file
        $exportedFile = $this->outputDir . '/en_US.neon';
        $this->assertFileExists($exportedFile);

        $content = file_get_contents($exportedFile);
        $translations = Neon::decode($content);

        $this->assertEquals('Welcome', $translations['welcome']);
        $this->assertEquals('Hello %s', $translations['hello']);
        $this->assertIsArray($translations['messages_count']);
        $this->assertEquals('You have %s message', $translations['messages_count'][1]);
    }

    /**
     * Test converting translations to CLDR format
     */
    public function testConvertToCldrCommand(): void
    {
        // Create legacy format NEON file
        $translations = [
            'days_count' => [
                0 => '%s dní',
                1 => '%s den',
                '2-4' => '%s dny',
                5 => '%s dní'
            ],
            'simple_text' => 'Simple translation'
        ];

        file_put_contents(
            $this->translationsDir . '/cs_CZ.neon',
            Neon::encode($translations, true)
        );

        // Execute convert command
        $application = new Application();
        $command = new ConvertToCldrCommand($this->connection);
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'source' => 'neon',
            'path' => $this->translationsDir,
            '--locale' => 'cs_CZ',
            '--output-dir' => $this->outputDir,
        ]);

        // Assert command succeeded
        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('converted: 1', $output);

        // Check converted file
        $convertedFile = $this->outputDir . '/cs_CZ.neon';
        $this->assertFileExists($convertedFile);

        $content = file_get_contents($convertedFile);
        $converted = Neon::decode($content);

        // Check CLDR format
        $this->assertArrayHasKey('days_count', $converted);
        $this->assertArrayHasKey('one', $converted['days_count']);
        $this->assertArrayHasKey('few', $converted['days_count']);
        $this->assertArrayHasKey('other', $converted['days_count']);
        $this->assertEquals('%s den', $converted['days_count']['one']);
        $this->assertEquals('%s dny', $converted['days_count']['few']);
        $this->assertEquals('%s dní', $converted['days_count']['other']);

        // Simple text should remain unchanged
        $this->assertEquals('Simple translation', $converted['simple_text']);
    }

    /**
     * Test import with overwrite option
     */
    public function testImportWithOverwrite(): void
    {
        // First import
        $this->insertTestData();

        // Create NEON file with updated translations
        $translations = [
            'welcome' => 'Welcome updated',
            'hello' => 'Hello %s updated',
        ];

        file_put_contents(
            $this->translationsDir . '/en_US.neon',
            Neon::encode($translations, true)
        );

        // Import without overwrite (should skip)
        $application = new Application();
        $command = new ImportNeonTranslationsCommand($this->connection);
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'directory' => $this->translationsDir,
            'module' => 'TestModule',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('skipped 2', $output);

        // Import with overwrite
        $commandTester->execute([
            'directory' => $this->translationsDir,
            'module' => 'TestModule',
            '--overwrite' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('updated 2', $output);

        // Verify updates in database
        $translation = $this->connection->query('
            SELECT t.value 
            FROM translations t
            JOIN translation_keys k ON t.key_id = k.id
            WHERE k.key = %s AND t.locale = %s
        ', 'welcome', 'en_US')->fetchSingle();

        $this->assertEquals('Welcome updated', $translation);
    }

    /**
     * Test export with filters
     */
    public function testExportWithFilters(): void
    {
        $this->insertTestData();

        // Export only specific keys
        $application = new Application();
        $command = new ExportNeonTranslationsCommand($this->connection);
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'module' => 'TestModule',
            'locale' => 'en_US',
            '--output-dir' => $this->outputDir,
            '--include-keys' => ['welcome', 'hello'],
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());

        // Check exported file contains only specified keys
        $exportedFile = $this->outputDir . '/en_US.neon';
        $translations = Neon::decode(file_get_contents($exportedFile));

        $this->assertArrayHasKey('welcome', $translations);
        $this->assertArrayHasKey('hello', $translations);
        $this->assertArrayNotHasKey('messages_count', $translations);
    }

    /**
     * Test dry-run mode
     */
    public function testDryRunMode(): void
    {
        // Create test NEON file
        $translations = [
            'days' => [
                1 => '1 day',
                2 => '%s days'
            ]
        ];

        file_put_contents(
            $this->translationsDir . '/en_US.neon',
            Neon::encode($translations, true)
        );

        // Execute convert command in dry-run mode
        $application = new Application();
        $command = new ConvertToCldrCommand($this->connection);
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'source' => 'neon',
            'path' => $this->translationsDir,
            '--dry-run' => true,
            '--output-dir' => $this->outputDir,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('dry-run mode', $output);

        // Output file should not be created
        $this->assertFileDoesNotExist($this->outputDir . '/en_US.neon');
    }

    // Helper methods

    private function createDatabaseStructure(): void
    {
        // Same structure as in DbDictionaryTest
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
        // Insert module
        $this->connection->query('
            INSERT INTO translation_modules (name) VALUES (%s)
        ', 'TestModule');

        $moduleId = $this->connection->getInsertId();

        // Insert keys
        $keys = [
            ['key' => 'welcome', 'type' => 'text'],
            ['key' => 'hello', 'type' => 'text'],
            ['key' => 'messages_count', 'type' => 'plural'],
        ];

        foreach ($keys as $i => $keyData) {
            $this->connection->query('
                INSERT INTO translation_keys (module_id, key, type)
                VALUES (%i, %s, %s)
            ', $moduleId, $keyData['key'], $keyData['type']);

            $keyId = $this->connection->getInsertId();

            // Insert translations
            if ($keyData['type'] === 'plural') {
                $this->connection->query('
                    INSERT INTO translations (key_id, locale, plural_values, is_translated)
                    VALUES (%i, %s, %s, 1)
                ', $keyId, 'en_US', json_encode([
                    1 => 'You have %s message',
                    2 => 'You have %s messages'
                ]));
            } else {
                $value = $keyData['key'] === 'welcome' ? 'Welcome' : 'Hello %s';
                $this->connection->query('
                    INSERT INTO translations (key_id, locale, value, is_translated)
                    VALUES (%i, %s, %s, 1)
                ', $keyId, 'en_US', $value);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
