<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Example 1: Using NEON Dictionary
function exampleWithNeonDictionary(): void
{
    // Directory with NEON files
    $translationsDir = __DIR__ . '/translations';

    // Cache directory
    $cacheDir = __DIR__ . '/temp/cache';

    // Create translation factory
    $factory = new \ElCheco\Translator\NeonDictionary\NeonDictionaryFactory(
        $translationsDir,
        $cacheDir,
        true // Automatically refresh translations
    );

    // Create translator and set locale
    $translator = new \ElCheco\Translator\Translator($factory);
    $translator->setLocale('en_US');

    // Basic translation
    echo "Basic translation:\n";
    echo $translator->translate('Welcome!') . "\n\n";

    // Translation with parameters
    echo "Translation with parameters:\n";
    echo $translator->translate('Hello %s!', 'John') . "\n\n";

    // Translation with pluralization
    echo "Translation with pluralization:\n";
    echo $translator->translate('You have %s unread messages.', 1) . "\n";
    echo $translator->translate('You have %s unread messages.', 2) . "\n";
    echo $translator->translate('You have %s unread messages.', 5) . "\n\n";

    // Change locale
    $translator->setLocale('cs_CZ');

    echo "After changing locale to cs_CZ:\n";
    echo $translator->translate('Welcome!') . "\n";
    echo $translator->translate('Hello %s!', 'John') . "\n";
    echo $translator->translate('You have %s unread messages.', 1) . "\n";
    echo $translator->translate('You have %s unread messages.', 2) . "\n";
    echo $translator->translate('You have %s unread messages.', 5) . "\n\n";

    // Using fallback locale
    $translator->setFallbackLocale('en_US');
    $translator->setLocale('fr_FR'); // We don't have French translations

    echo "Using fallback locale (en_US) when fr_FR is not available:\n";
    echo $translator->translate('Welcome!') . "\n";
}

// Example 2: Using Database Dictionary
function exampleWithDatabaseDictionary(\Dibi\Connection $connection): void
{
    // Create Database Dictionary Factory
    $factory = new \ElCheco\Translator\DbDictionary\DbDictionaryFactory(
        $connection,
        'Common', // Module name
        true      // Track usage
    );

    // Create translator and set locale
    $translator = new \ElCheco\Translator\Translator($factory);
    $translator->setLocale('en_US');

    // Basic translation
    echo "Basic translation from database:\n";
    echo $translator->translate('Welcome to our website') . "\n\n";

    // Translation with parameters
    echo "Translation with parameters from database:\n";
    echo $translator->translate('Hello %s', 'Jane') . "\n\n";

    // Translation with pluralization
    echo "Translation with pluralization from database:\n";
    echo $translator->translate('You have %s new messages', 1) . "\n";
    echo $translator->translate('You have %s new messages', 3) . "\n";

    // Change locale
    $translator->setLocale('cs_CZ');

    echo "\nAfter changing locale to cs_CZ:\n";
    echo $translator->translate('Welcome to our website') . "\n";
    echo $translator->translate('Hello %s', 'Jane') . "\n";
    echo $translator->translate('You have %s new messages', 1) . "\n";
    echo $translator->translate('You have %s new messages', 3) . "\n";
    echo $translator->translate('You have %s new messages', 5) . "\n";

    // Save usage statistics (would typically be done on application shutdown)
    $dictionary = $translator->getDictionary();
    if ($dictionary instanceof \ElCheco\Translator\DbDictionary\DbDictionary) {
        $dictionary->saveUsageStats();
    }
}

// Run examples
echo "=== Example with NEON Dictionary ===\n\n";
exampleWithNeonDictionary();

echo "\n\n=== Example with Database Dictionary ===\n\n";
echo "To run this example, you need a database connection.\n";
echo "Uncomment the following code and provide your database credentials.\n";

/*
// Database connection example
$connection = new \Dibi\Connection([
    'driver'   => 'mysqli',
    'host'     => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'translations',
    'charset'  => 'utf8mb4',
]);

exampleWithDatabaseDictionary($connection);
*/

use ElCheco\Translator\PluralRulesInterface;
use ElCheco\Translator\Translation;

class MyPluralRules implements PluralRulesInterface
{
    public static function getNormalizedCount(string $locale, int $count): int
    {
        // Your custom rules here
        // For example, special rules for a specific dialect
        if ($locale === 'fr_CA') {
            // French Canadian specific rules
            return $count === 1 ? 1 : 2;
        }

        // For other locales, fall back to default rules
        return \ElCheco\Translator\PluralRules::getNormalizedCount($locale, $count);
    }
}

// Register your custom rules
Translation::setCustomPluralRules(new MyPluralRules());
