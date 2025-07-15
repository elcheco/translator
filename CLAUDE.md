# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP translation library providing internationalization support with both legacy numeric plural formats and modern Unicode CLDR standard support. The library integrates with Nette Framework and supports both file-based (NEON) and database storage backends.

## Development Commands

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit          # Unit tests only
composer test:integration   # Integration tests
composer test:cldr          # CLDR-specific tests
composer test:console       # Console command tests
composer test:coverage      # Generate coverage report
composer test:demo          # Run demo test script

# Run static analysis
vendor/bin/phpstan analyze

# Run a single test file
vendor/bin/phpunit tests/Integration/DbDictionaryTest.php

# Run tests matching a pattern
vendor/bin/phpunit --filter testMethod
```

## Architecture Overview

### Core Components

1. **Translation System**
   - `Translator` - Main implementation handling translation logic
   - `CldrTranslator` - Extended version with CLDR plural rules support
   - `Translation` / `CldrTranslation` - Value objects representing translations

2. **Storage Backends**
   - **NeonDictionary**: File-based storage using NEON format
     - Supports caching and watching for file changes
     - Loads translations from `lang/*.neon` files
   - **DbDictionary**: Database storage with usage tracking
     - Tracks untranslated strings automatically
     - Supports batch operations via console commands
     - Requires specific database schema (see README.md)

3. **CLDR Integration**
   - Full Unicode CLDR plural rules implementation
   - Supports all plural categories: zero, one, two, few, many, other
   - Automatic conversion from legacy numeric formats
   - Czech/Slovak decimal plural rules support

### Key Design Patterns

- **Strategy Pattern**: Dictionary implementations are interchangeable via `DictionaryInterface`
- **Factory Pattern**: `DictionaryFactoryInterface` creates appropriate dictionary instances
- **Decorator Pattern**: CLDR versions extend base classes with additional functionality
- **Dependency Injection**: Full Nette DI integration via Extension class

### Database Schema

When using DbDictionary, the following tables are required:
- `dictionary`: Stores translations
- `dictionary_untranslated`: Tracks missing translations
- `dictionary_aggregated_temp`: Temporary storage for batch operations

### Console Commands

Located in `src/Translator/Console/`:
- `ImportNeonTranslationsCommand`: Import from NEON to database
- `ExportNeonTranslationsCommand`: Export from database to NEON
- `ConvertToCldrCommand`: Convert legacy translations to CLDR format

### Testing Approach

- Tests are organized by type: Unit, Integration, CLDR, Console
- Fixtures in `tests/lang/` contain sample translations
- Database tests use in-memory SQLite when possible
- Integration tests verify Latte template integration

### Important Implementation Details

1. **Shutdown Handler**: DbDictionary registers a shutdown handler to save untranslated strings
2. **Number Formatting**: Automatic locale-aware number formatting in translations
3. **Fallback Chain**: Missing translations fall back to default locale
4. **Performance**: File-based dictionaries support caching to avoid repeated file reads
5. **Backward Compatibility**: Legacy numeric plural formats are still supported alongside CLDR