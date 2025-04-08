# ElCheco Translator
[![Downloads this Month](https://img.shields.io/packagist/dm/elcheco/translator.svg)](https://packagist.org/packages/elcheco/translator) [![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/elcheco/translator/blob/master/LICENSE)

A powerful and flexible translation library for PHP applications with support for both NEON files and database storage.

## Features

- Support for multiple translation storage backends (NEON files, Database)
- Pluralization rules for many languages
- Fallback locale support
- Translation usage tracking
- Console commands for importing and exporting translations
- Compatible with Nette Framework

## Requirements

- PHP 8.3 or higher
- Nette Framework 3.0 or higher (if using with Nette)
- Dibi database library (if using database storage)

## Installation

```bash
composer require elcheco/translator
```

## Configuration

### Basic Configuration (Nette DI)

```neon
extensions:
    translator: ElCheco\Translator\Extension
```

### NEON File-Based Translations

```neon
translator:
    default: en_US     # Default locale
    fallback: en_US    # Fallback locale
    debugMode: %debugMode%
    dictionary:
        factory: ElCheco\Translator\NeonDictionary\NeonDictionaryFactory
        args:
            directory: %appDir%/translations    # Directory with NEON files
            cache: %tempDir%/cache/translations # Cache directory
            autoRefresh: %debugMode%            # Auto refresh translations
```

### Database-Based Translations

```neon
translator:
    default: en_US     # Default locale
    fallback: en_US    # Fallback locale
    dictionary:
        factory: ElCheco\Translator\DbDictionary\DbDictionaryFactory
        args:
            - @Dibi\Connection  # Database connection
            - MyModule           # Module name
            - true               # Track usage
    commands:
        - ElCheco\Translator\Console\ImportNeonTranslationsCommand(@Dibi\Connection)
        - ElCheco\Translator\Console\ExportNeonTranslationsCommand(@Dibi\Connection)
```

## Usage

### In Presenters or Services

```php
use ElCheco\Translator\Translator;

class BasePresenter extends \Nette\Application\UI\Presenter
{
    private Translator $translator;

    public function injectTranslator(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function beforeRender()
    {
        // Set translator for templates
        $this->template->setTranslator($this->translator);
    }

    public function handleSwitchLocale(string $locale)
    {
        $this->translator->setLocale($locale);
        $this->redirect('this');
    }
}
```

### In Templates (Latte)

```latte
{* Simple translation *}
<h1>{_'Welcome to our website'}</h1>

{* Translation with parameters *}
<p>{_'Hello %s', $userName}</p>

{* Translation with pluralization *}
<p>{_'You have %s new messages', $count}</p>
```

### Direct Usage in PHP

```php
use ElCheco\Translator\Translator;

$translator = new Translator($dictionaryFactory);
$translator->setLocale('en_US');

// Simple translation
echo $translator->translate('Welcome to our website');

// Translation with parameters
echo $translator->translate('Hello %s', 'John');

// Translation with pluralization
echo $translator->translate('You have %s new messages', 5);
```

## NEON Translation Files Format

Translation files are named according to locale, e.g., `en_US.neon`, `cs_CZ.neon`.

### Simple Translations

```neon
# en_US.neon
Welcome to our website: Welcome to our website
Hello %s: Hello %s
```

### Translations with Pluralization

```neon
# en_US.neon
You have %s new messages:
    1: You have %s new message
    2: You have %s new messages

%s bedrooms:
    1: %s bedroom
    2: %s bedrooms
```

### Translations with Pluralization Ranges

```neon
# cs_CZ.neon
You have %s points:
    1: Máš %s bod
    "2-4": Máš %s body
    5: Máš %s bodů
```

or simplified

```neon
# cs_CZ.neon
You have %s points:
    1: Máš %s bod
    2: Máš %s body
    5: Máš %s bodů
```

## Command Line Tools

### Import Translations from NEON to Database

```bash
php bin/console translations:import-neon /path/to/neon/files ModuleName \
    --locale=en_US \
    --mark-as-translated \
    --mark-as-approved \
    --overwrite
```

Options:
- `--locale` or `-l`: Import only a specific locale
- `--mark-as-approved` or `-a`: Mark imported translations as approved
- `--overwrite` or `-o`: Overwrite existing translations

### Export Translations from Database to NEON

```bash
php bin/console translations:export-neon ModuleName en_US \
    --output-dir=./translations \
    --include-keys=key1,key2 \
    --include-untranslated
    
```

Options:
- `--output-dir` or `-o`: Output directory for NEON files
- `--include-keys` or `-k`: Include only specific keys
- `--include-untranslated` or `-u`: Include keys without translations

## Database Structure

The database storage uses the following tables:

- `translation_modules`: Modules (groups of keys)
- `translation_keys`: Translation keys
- `translations`: Actual translations for different locales

See the included `db/structure.sql` file for complete database schema.

## Testing

```bash
composer install
vendor/bin/tester tests/
```

## Examples

### NEON Dictionary With Fallback Locale

```php
use ElCheco\Translator\NeonDictionary\NeonDictionaryFactory;
use ElCheco\Translator\Translator;

$factory = new NeonDictionaryFactory(
    __DIR__ . '/translations',  // Directory with translation files
    __DIR__ . '/temp/cache',    // Cache directory
    true                        // Auto refresh
);

$translator = new Translator($factory);
$translator->setLocale('fr_FR');      // Primary locale
$translator->setFallbackLocale('en_US'); // Fallback locale
```

### DB Dictionary With Usage Tracking

```php
use ElCheco\Translator\DbDictionary\DbDictionaryFactory;
use ElCheco\Translator\Translator;

$factory = new DbDictionaryFactory(
    $connection,  // Dibi or other DB connection
    'Frontend',   // Module name
    true          // Track usage
);

$translator = new Translator($factory);
$translator->setLocale('de_DE');
```

## Advanced Usage

### Customizing Plural Rules

ElCheco Translator includes built-in plural rules for many languages. If you need to add custom rules, you can extend the `PluralRules` class:

```php
use ElCheco\Translator\PluralRules;

class MyPluralRules extends PluralRules
{
    // Add custom language rules
}
```

### Creating Custom Dictionary Implementations

You can create your own dictionary implementation by implementing the `DictionaryInterface`:

```php
use ElCheco\Translator\DictionaryInterface;

class MyCustomDictionary implements DictionaryInterface
{
    public function get(string $message): string|array
    {
        // Your implementation
    }
    
    public function has(string $message): bool
    {
        // Your implementation
    }
}
```

Note:
Inspired by [rostenkowski/translate](https://github.com/rostenkowski/translate), but I required support for Nette Framework versions ^3.2 and ^4.0, as well as a fallback translation option. I also refactored the pluralization to make it more naturally understandable and to support complex plural forms, such as those found in many Slavic languages. And newly added the DbDictionary feature for improved development and management of translations.


## License

MIT License
