# Command-Line Translator Test

This directory contains a command-line script for testing the CLDR plural functionality of the translator without requiring a full Nette application.

## Usage

Run the script from the command line:

```bash
php examples/bin/test-translator.php
```

## What the Script Does

The script:

1. Creates an in-memory SQLite database with the necessary tables for translations
2. Inserts test data with Czech CLDR plural forms for the `room_count` key
3. Creates a `CldrDbDictionaryFactory` and `CldrTranslator`
4. Tests the translator with various count values (0, 1, 2, 3, 4, 5, 10, 21, 22, 25, 1.5, 2.5)
5. Compares the results with the expected output
6. Displays a summary of the test results

## Expected Output

If the translator is working correctly, you should see output like this:

```
Testing Czech plural forms for 'room_count':
==========================================

Count: 0
Result: 0 pokojů
Expected: 0 pokojů
Correct: ✓

Count: 1
Result: 1 pokoj
Expected: 1 pokoj
Correct: ✓

Count: 2
Result: 2 pokoje
Expected: 2 pokoje
Correct: ✓

...

If all tests show ✓, the translator is working correctly with CLDR plurals.
If any test shows ✗, check your configuration and database translations.
```

## Requirements

- PHP 8.3 or higher
- PHP extensions: intl, sqlite3
- Composer dependencies installed

## Troubleshooting

If you encounter errors:

1. Make sure you have the required PHP extensions installed:
   ```bash
   php -m | grep -E 'intl|sqlite3'
   ```

2. Make sure you've installed the dependencies:
   ```bash
   composer install
   ```

3. Check for PHP errors in the output.

## Modifying the Test

You can modify the script to test different languages or translation keys:

1. Change the locale in `$translator->setLocale('cs_CZ')` to test a different language
2. Modify the `insertTestData()` function to insert different translations
3. Add or remove count values in the `$counts` array to test different numbers
