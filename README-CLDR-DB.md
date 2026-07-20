# Using CLDR Plurals with Database Translations

This document explains how to use CLDR plurals with database translations in your Nette project.

## Configuration

To use CLDR plurals with database translations, you need to update your `config.neon` file to use the `CldrDbDictionaryFactory` instead of the regular `DbDictionaryFactory`. Here's an example configuration:

```neon
extensions:
    translator: ElCheco\Translator\Extension

translator:
    default: cs_CZ
    fallback: en_US
    # Database Dictionary with CLDR support
    dictionary:
        factory: ElCheco\Translator\Cldr\CldrDbDictionaryFactory
        args:
            - @Dibi\Connection
            - Frontend
            - false  # disable usage tracking if not needed

    commands:
        - ElCheco\Translator\Console\ImportNeonTranslationsCommand(@Dibi\Connection)
        - ElCheco\Translator\Console\ExportNeonTranslationsCommand(@Dibi\Connection)
        - ElCheco\Translator\Console\ConvertToCldrCommand(@Dibi\Connection)

services:
    translator.translator:
        factory: ElCheco\Translator\Cldr\CldrTranslator
        arguments:
            - @translator.dictionaryFactory
            - null                        # optional
            - %debugMode%                 # optional
        setup:
            - setFallbackLocale(%translator.fallback%)
            - setLocale(%translator.default%)
            - setCldrEnabled(true)
```

## Database Structure

Make sure your database has the correct structure for storing translations. The `plural_values` column in the `translations` table should contain JSON-encoded CLDR plural forms. For Czech, the plural forms should look like this:

```json
{
    "one": "{count} pokoj",
    "few": "{count} pokoje",
    "many": "{count, number} pokoje",
    "other": "{count} pokojů"
}
```

## Testing in Templates

To test CLDR plurals in your templates, you can use the translator with the count parameter:

```latte
{* Basic usage *}
{_'room_count', $accommodation->numberOfRooms}

{* Testing different counts *}
<ul>
    <li>{_'room_count', 0}</li>
    <li>{_'room_count', 1}</li>
    <li>{_'room_count', 2}</li>
    <li>{_'room_count', 3}</li>
    <li>{_'room_count', 4}</li>
    <li>{_'room_count', 5}</li>
    <li>{_'room_count', 1.5}</li>
</ul>
```

This should output:

```
- 0 pokojů
- 1 pokoj
- 2 pokoje
- 3 pokoje
- 4 pokoje
- 5 pokojů
- 1,5 pokoje
```

## Creating a Test Page

You can create a simple test page to verify that CLDR plurals are working correctly:

```php
// app/Presenters/TestPresenter.php
namespace App\Presenters;

use Nette\Application\UI\Presenter;

class TestPresenter extends Presenter
{
    public function renderDefault(): void
    {
        $this->template->counts = [0, 1, 2, 3, 4, 5, 10, 1.5, 2.5];
    }
}
```

```latte
{* app/Presenters/templates/Test/default.latte *}
{block content}
<h1>CLDR Plural Test</h1>

<h2>Room Count Test</h2>
<ul>
    {foreach $counts as $count}
        <li>{$count}: {_'room_count', $count}</li>
    {/foreach}
</ul>
{/block}
```

## Troubleshooting

If you're having issues with CLDR plurals:

1. Make sure the `intl` PHP extension is installed and enabled.
2. Verify that your database contains the correct CLDR plural forms.
3. Check that you're using `CldrTranslator` and `CldrDbDictionaryFactory` in your configuration.
4. Enable debug mode to see more detailed error messages:
   ```php
   $translator->setDebugMode(true);
   ```

## Converting Legacy Translations to CLDR Format

If you have legacy translations with numeric keys or ranges (e.g., "0:", "1:", "2-4:"), you can convert them to CLDR format using the `ConvertToCldrCommand`:

```bash
php bin/console translations:convert-to-cldr
```

This command will convert legacy plural forms to CLDR format for all languages in your database.
