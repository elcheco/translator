<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// This script demonstrates how to test the translator with CLDR plurals
// from the command line, without a full Nette application.

// Create a database connection (using SQLite for simplicity)
$connection = new \Dibi\Connection([
    'driver' => 'sqlite3',
    'database' => ':memory:',
]);

// Set up database schema
setupDatabase($connection);

// Insert test data
insertTestData($connection);

// Create CldrDbDictionaryFactory
$factory = new \ElCheco\Translator\Cldr\CldrDbDictionaryFactory($connection, 'TestModule', false);

// Create CldrTranslator
$translator = new \ElCheco\Translator\Cldr\CldrTranslator($factory);
$translator->setLocale('cs_CZ');
$translator->setCldrEnabled(true);

// Test different count values
$counts = [0, 1, 2, 3, 4, 5, 10, 21, 22, 25, 1.5, 2.5];

echo "Testing Czech plural forms for 'room_count':\n";
echo "==========================================\n\n";

foreach ($counts as $count) {
    $result = $translator->translate('room_count', $count);
    $expected = getExpectedForm($count);

    echo sprintf(
        "Count: %s\nResult: %s\nExpected: %s\nCorrect: %s\n\n",
        $count,
        $result,
        $expected,
        $result === $expected ? '✓' : '✗'
    );
}

echo "If all tests show ✓, the translator is working correctly with CLDR plurals.\n";
echo "If any test shows ✗, check your configuration and database translations.\n";

/**
 * Set up database schema
 */
function setupDatabase(\Dibi\Connection $connection): void
{
    // Create modules table
    $connection->query('
        CREATE TABLE [translation_modules] (
            [id] INTEGER PRIMARY KEY AUTOINCREMENT,
            [name] TEXT NOT NULL,
            [is_active] INTEGER NOT NULL DEFAULT 1
        )
    ');

    // Create keys table
    $connection->query('
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
    $connection->query('
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
function insertTestData(\Dibi\Connection $connection): void
{
    // Insert module
    $connection->query('
        INSERT INTO [translation_modules] ([name], [is_active])
        VALUES (%s, %i)
    ', 'TestModule', 1);

    $moduleId = $connection->getInsertId();

    // Insert room_count key
    $connection->query('
        INSERT INTO [translation_keys] ([module_id], [key], [type])
        VALUES (%i, %s, %s)
    ', $moduleId, 'room_count', 'plural');

    $keyId = $connection->getInsertId();

    // Insert Czech translation with CLDR plural forms
    $pluralValues = json_encode([
        'one' => '{count} pokoj',
        'few' => '{count} pokoje',
        'many' => '{count, number} pokoje',
        'other' => '{count} pokojů'
    ]);

    $connection->query('
        INSERT INTO [translations] ([key_id], [locale], [value], [plural_values])
        VALUES (%i, %s, %s, %s)
    ', $keyId, 'cs_CZ', null, $pluralValues);
}

/**
 * Get expected form for a count value
 */
function getExpectedForm($count): string
{
    if ($count == 1) {
        return "$count pokoj"; // one form
    } elseif ($count >= 2 && $count <= 4 && (int)$count == $count) {
        return "$count pokoje"; // few form
    } elseif (is_float($count)) {
        // Format decimal number with comma as decimal separator (Czech locale)
        $formattedCount = str_replace('.', ',', (string)$count);
        return "$formattedCount pokoje"; // many form
    } else {
        return "$count pokojů"; // other form
    }
}
