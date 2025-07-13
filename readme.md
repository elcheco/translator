# ElCheco Translator
[![Downloads this Month](https://img.shields.io/packagist/dm/elcheco/translator.svg)](https://packagist.org/packages/elcheco/translator) [![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/elcheco/translator/blob/master/LICENSE)

A powerful and flexible translation library for PHP applications with support for both NEON files and database storage, featuring full Unicode CLDR plural rules support.

## Features

- **CLDR Plural Rules Support** - Full Unicode CLDR compliance for accurate pluralization
- **Legacy Format Compatibility** - Seamless support for existing numeric plural formats
- **Multiple Storage Backends** - NEON files or database storage
- **Advanced Pluralization** - Support for all CLDR categories (zero, one, two, few, many, other)
- **Decimal Number Support** - Correct handling of decimal plurals (e.g., Czech uses 'many' for decimals)
- **Locale-Specific Number Formatting** - Automatic number formatting based on locale
- **Translation Usage Tracking** - Monitor which translations are actually used
- **Console Commands** - Import, export, and convert translations
- **Migration Tools** - Convert legacy formats to CLDR standard
- **Fallback Locale Support** - Automatic fallback when translations are missing
- **Compatible with Nette Framework** - Full integration with Nette DI

## What's New in Version 2.0

- 🎉 **CLDR Plural Rules** - Industry-standard pluralization for 100+ languages
- 🔢 **Decimal Number Support** - Proper handling of fractional numbers in plurals
- 🔄 **Automatic Format Detection** - Use legacy and CLDR formats in the same project
- 🛠️ **Migration Tools** - Convert existing translations to CLDR format
- 📊 **Enhanced Database Support** - Store CLDR patterns and ICU MessageFormat
- ⚡ **Zero Breaking Changes** - Full backward compatibility maintained

## Requirements

- PHP 8.3 or higher
- Nette Framework 3.0 or higher (if using with Nette)
- Dibi database library (if using database storage)
- PHP Intl extension (recommended for CLDR support)

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
        - ElCheco\Translator\Console\ConvertToCldrCommand(@Dibi\Connection) # NEW!
```

## CLDR Plural Rules Support

The translator now supports the Unicode CLDR (Common Locale Data Repository) standard for plural rules, providing linguistically correct pluralization for all languages.

### What are CLDR Plural Rules?

CLDR defines standardized categories for plural forms:
- **zero**: For zero items (some languages have special forms)
- **one**: For singular (but not always just 1!)
- **two**: For dual forms (e.g., Slovenian)
- **few**: For small quantities (e.g., 2-4 in Czech)
- **many**: For larger quantities or special cases (e.g., decimals in Czech)
- **other**: The default/general plural form

### CLDR vs Legacy Format

The translator supports both formats simultaneously:

#### Legacy Format (still supported):
```neon
messages_count:
    0: You have no messages
    1: You have %s message
    2: You have %s messages

# Czech with ranges
days_count:
    1: %s den
    "2-4": %s dny
    5: %s dní
```

#### CLDR Format (recommended):
```neon
messages_count:
    zero: You have no messages
    one: You have one message
    other: You have {count} messages

# Czech with proper decimal support
days_count:
    one: {count} den
    few: {count} dny
    many: {count} dne      # for decimals like 1.5, 2.5
    other: {count} dní
```

### Special Case: Czech Decimals

Czech (and Slovak) use the `many` category specifically for decimal numbers:

```neon
# Czech translations
distance:
    one: "{count} kilometr"      # 1 kilometr
    few: "{count} kilometry"     # 2, 3, 4 kilometry  
    many: "{count} kilometru"    # 1.5 kilometru, 2.5 kilometru
    other: "{count} kilometrů"   # 0, 5, 6... kilometrů
```

This ensures grammatically correct output:
- ✅ 1,5 kilometru (not "kilometrů" or "kilometr")
- ✅ 2,5 kilometru (not "kilometry")

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

{* Legacy plural format *}
<p>{_'You have %s new messages', $count}</p>

{* CLDR format with automatic number formatting *}
<p>{_'items_count', $itemCount}</p>

{* Decimal numbers are formatted correctly *}
<p>{_'distance_km', 1.5}</p> {* Czech: "1,5 kilometru" *}
```

### Direct Usage in PHP

```php
use ElCheco\Translator\Translator;

$translator = new Translator($dictionaryFactory);
$translator->setLocale('cs_CZ');

// Simple translation
echo $translator->translate('Welcome'); // "Vítejte"

// Legacy plural format
echo $translator->translate('You have %s messages', 5); // "Máte 5 zpráv"

// CLDR format
echo $translator->translate('days_count', 1);    // "1 den"
echo $translator->translate('days_count', 2);    // "2 dny"
echo $translator->translate('days_count', 1.5);  // "1,5 dne" (decimal → many)
echo $translator->translate('days_count', 5);    // "5 dní"
```

## NEON Translation Files

Translation files support both legacy and CLDR formats:

### Simple Translations

```neon
# en_US.neon
Welcome: Welcome to our website
"Hello %s": Hello %s
```

### Legacy Plural Format

```neon
# cs_CZ.neon
"You have %s messages":
    0: Nemáte žádné zprávy
    1: Máte %s zprávu
    "2-4": Máte %s zprávy
    5: Máte %s zpráv
```

### CLDR Format (Recommended)

```neon
# en_US.neon
items_count:
    zero: You have no items
    one: You have one item
    other: You have {count} items

# cs_CZ.neon
items_count:
    one: Máte {count} položku
    few: Máte {count} položky
    many: Máte {count} položky    # for decimals
    other: Máte {count} položek
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

### Export Translations from Database to NEON

```bash
php bin/console translations:export-neon ModuleName en_US \
    --output-dir=./translations \
    --include-keys=key1,key2 \
    --include-untranslated
```

### Convert Legacy Translations to CLDR Format (NEW!)

```bash
# Convert NEON files
php bin/console translations:convert-to-cldr neon /path/to/translations \
    --locale=cs_CZ \
    --output-dir=./translations/cldr \
    --backup

# Convert database translations
php bin/console translations:convert-to-cldr database ModuleName \
    --locale=cs_CZ \
    --dry-run
```

## Database Structure

The database schema has been enhanced to support CLDR:

```sql
-- New column for format type
ALTER TABLE `translation_keys` 
ADD COLUMN `format_type` ENUM('sprintf', 'icu') DEFAULT 'sprintf';

-- Stores ICU MessageFormat patterns
ALTER TABLE `translation_keys`
ADD COLUMN `cldr_message_pattern` TEXT NULL;
```

## Number Formatting

The translator automatically formats numbers according to locale:

```php
$translator->setLocale('en_US');
echo $translator->translate('distance_km', 1234.5); // "1,234.5 kilometers"

$translator->setLocale('cs_CZ');
echo $translator->translate('distance_km', 1234.5); // "1 234,5 kilometru"

$translator->setLocale('de_DE');
echo $translator->translate('distance_km', 1234.5); // "1.234,5 Kilometer"
```

## Migration Guide

### Migrating to CLDR Format

1. **Automatic Detection**: The translator automatically detects and handles both formats
2. **Gradual Migration**: You can migrate translations one by one
3. **Migration Tool**: Use the console command to convert existing translations

```bash
# Backup and convert
php bin/console translations:convert-to-cldr neon ./translations \
    --backup \
    --output-dir=./translations
```

### Example Migration

Before (Legacy):
```neon
days:
    0: žádný den
    1: %s den
    "2-4": %s dny
    5: %s dní
```

After (CLDR):
```neon
days:
    zero: žádný den
    one: {count} den
    few: {count} dny
    many: {count} dne     # for decimals
    other: {count} dní
```

## Advanced Features

### Custom Plural Rules

```php
use ElCheco\Translator\Cldr\CldrPluralRules;

// Get plural category for a number
$category = CldrPluralRules::getPluralCategory('cs_CZ', 1.5); // 'many'

// Get available categories for a locale
$categories = CldrPluralRules::getAvailableCategories('cs_CZ'); 
// ['one', 'few', 'many', 'other']
```

### Usage Tracking (Database Only)

```php
// Enable usage tracking
$factory = new DbDictionaryFactory($connection, 'Module', true);

// Later, save statistics
$dictionary->saveUsageStats();

// Query usage data
SELECT key, usage_count FROM translation_keys ORDER BY usage_count DESC;
```

## Testing

```bash
# Run all tests
composer test

# Run specific test suites
vendor/bin/phpunit --testsuite "CLDR Tests"
vendor/bin/phpunit --filter testCzechDecimalSupport
```

## Language Support

The translator includes built-in CLDR plural rules for 100+ languages, including:

- **Simple plurals** (one/other): English, German, Dutch, Spanish, Italian
- **Slavic languages**: Czech, Slovak, Polish, Russian, Ukrainian, Croatian
- **Complex plurals**: Arabic (6 forms), Irish, Maltese, Lithuanian
- **No plurals**: Japanese, Chinese, Thai, Vietnamese
- **Special decimals**: Czech/Slovak (many), Lithuanian (many), Romanian (few)

## Examples

### E-commerce Site

```neon
# products.cs_CZ.neon
product_count:
    zero: Žádné produkty
    one: "{count} produkt"
    few: "{count} produkty"
    many: "{count} produktu"    # 1,5 produktu
    other: "{count} produktů"

in_stock:
    zero: Vyprodáno
    one: "Poslední kus"
    few: "Posledních {count} kusy"
    other: "Skladem {count} kusů"
```

### Weather App

```neon
# weather.cs_CZ.neon
temperature:
    one: "{count} stupeň"
    few: "{count} stupně"
    many: "{count} stupně"      # 20,5 stupně
    other: "{count} stupňů"

days_forecast:
    one: "Předpověď na {count} den"
    few: "Předpověď na {count} dny"
    many: "Předpověď na {count} dne"   # 1,5 dne
    other: "Předpověď na {count} dní"
```

## Changelog

### Version 2.0.0
- Added full Unicode CLDR plural rules support
- Added decimal number handling for all languages
- Added ICU MessageFormat support
- Added migration tools for legacy → CLDR conversion
- Enhanced database schema for CLDR storage
- Maintained 100% backward compatibility

### Version 1.x
- Basic translation support
- Legacy plural forms
- Database and NEON storage
- Import/export commands

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

MIT License

## Credits

Inspired by [rostenkowski/translate](https://github.com/rostenkowski/translate), enhanced with:
- Full CLDR plural rules support
- Decimal number handling
- Modern PHP 8.3+ features
- Enhanced database functionality
- Comprehensive migration tools

## Support

For questions and support:
- Create an issue on GitHub
- Check the [documentation](https://github.com/elcheco/translator/wiki)
- See [examples](https://github.com/elcheco/translator/tree/master/examples)
