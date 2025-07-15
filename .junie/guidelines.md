# ElCheco Translator Development Guidelines

This document provides essential information for developers working on the ElCheco Translator project.

## Build/Configuration Instructions

### Requirements
- PHP 8.3 or higher
- PHP extensions: intl, tokenizer
- Composer for dependency management

### Installation
1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

### Configuration
The translator can be configured to use either NEON files or a database for storing translations:

#### NEON File-Based Configuration
```php
$translationsDir = __DIR__ . '/translations';
$cacheDir = __DIR__ . '/temp/cache';
$factory = new NeonDictionaryFactory($translationsDir, $cacheDir);
$translator = new Translator($factory);
$translator->setLocale('en_US');
```

#### Database-Based Configuration
```php
$connection = new \Dibi\Connection([/* database configuration */]);
$factory = new DbDictionaryFactory($connection, 'ModuleName', true); // true enables usage tracking
$translator = new Translator($factory);
$translator->setLocale('en_US');
```

#### Nette Framework Integration
Add to your config.neon:
```neon
extensions:
    translator: ElCheco\Translator\Extension

translator:
    default: en_US     # Default locale
    fallback: en_US    # Fallback locale
    dictionary:
        factory: ElCheco\Translator\NeonDictionary\NeonDictionaryFactory
        args:
            directory: %appDir%/translations
            cache: %tempDir%/cache/translations
            autoRefresh: %debugMode%
```

## Testing Information

### Running Tests
The project uses PHPUnit for testing. Several test suites are available:

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit        # Unit tests
composer test:integration # Integration tests
composer test:cldr        # CLDR-specific tests
composer test:console     # Console command tests

# Run with coverage report
composer test:coverage
```

You can also run specific test files directly:
```bash
vendor/bin/phpunit tests/Unit/TranslatorTest.php
```

Or specific test methods:
```bash
vendor/bin/phpunit --filter testSimpleTranslations tests/Unit/TranslatorTest.php
```

### Test Structure
Tests are organized into several directories:
- `tests/Unit/`: Unit tests for individual components
- `tests/Integration/`: Integration tests for components working together
- `tests/Cldr/`: Tests for CLDR plural rules functionality
- `tests/Console/`: Tests for console commands
- `tests/fixtures/`: Test fixtures including translation files

### Adding New Tests
1. Create a new test class in the appropriate directory
2. Extend `PHPUnit\Framework\TestCase`
3. Implement `setUp()` and `tearDown()` methods if needed
4. Add test methods prefixed with `test`

Example of a simple test:
```php
<?php
declare(strict_types=1);

namespace ElCheco\Translator\Tests\Unit;

use ElCheco\Translator\NeonDictionary\NeonDictionaryFactory;
use ElCheco\Translator\Translator;
use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase
{
    private string $translationsDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translationsDir = __DIR__ . '/../fixtures/translations';
        $this->cacheDir = sys_get_temp_dir() . '/translator_tests_' . uniqid();
        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up cache directory
        $this->removeDirectory($this->cacheDir);
    }

    public function testBasicTranslation(): void
    {
        $translator = $this->createTranslator('en_US');
        
        // Test basic translation
        $this->assertEquals('Welcome to our website', $translator->translate('Welcome'));
        
        // Test translation with parameters
        $this->assertEquals('Hello John', $translator->translate('Hello %s', 'John'));
    }

    private function createTranslator(string $locale): Translator
    {
        $factory = new NeonDictionaryFactory($this->translationsDir, $this->cacheDir);
        $translator = new Translator($factory);
        $translator->setLocale($locale);
        return $translator;
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
```

## Additional Development Information

### Code Style
- The project follows PSR-12 coding standards
- Use strict typing (`declare(strict_types=1);`) in all PHP files
- Use type hints for method parameters and return types

### Translation Files
- NEON files should be named according to locale (e.g., `en_US.neon`, `cs_CZ.neon`)
- Legacy plural format uses numeric keys or ranges (e.g., `0:`, `1:`, `"2-4":`)
- CLDR format uses category names (e.g., `zero:`, `one:`, `few:`, `many:`, `other:`)

### Database Structure
The database schema includes tables for translation keys and translations:
- `translation_keys`: Stores translation keys and metadata
- `translation_values`: Stores translations for each key and locale
- `translation_usage`: Tracks usage statistics (if enabled)

See `db/structure.sql` for the complete schema.

### Working with CLDR Plural Rules
The translator supports the Unicode CLDR standard for plural rules:
- Use `CldrPluralRules::getPluralCategory($locale, $number)` to get the plural category for a number
- Use `CldrPluralRules::getAvailableCategories($locale)` to get available categories for a locale

### Console Commands
The project includes several console commands for managing translations:
- `translations:import-neon`: Import translations from NEON files to database
- `translations:export-neon`: Export translations from database to NEON files
- `translations:convert-to-cldr`: Convert legacy translations to CLDR format

### Debugging
- Set `$translator->setDebugMode(true)` to enable debug mode
- In debug mode, missing translations will throw exceptions
- Use a PSR-3 compatible logger for logging:
  ```php
  $translator->setLogger($logger);
  ```

### Performance Considerations
- Use caching in production environments
- For NEON-based translations, set `autoRefresh: false` in production
- For database-based translations, consider using a connection pool
- Usage tracking adds overhead; disable it if not needed
