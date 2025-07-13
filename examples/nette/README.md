# Testing CLDR Plurals in Nette Templates

This directory contains example files for testing CLDR plurals in Nette templates. These examples demonstrate how to verify that the translator is correctly handling plural forms in Czech and other languages.

## Setup Instructions

1. Copy the `TranslatorTestPresenter.php` file to your Nette application's presenters directory (typically `app/Presenters/`).
2. Create a directory for the presenter's templates: `app/Presenters/templates/TranslatorTest/`.
3. Copy the `templates/TranslatorTest/default.latte` file to this directory.
4. Make sure your application is configured to use `CldrTranslator` and `CldrDbDictionaryFactory` as shown in the [README-CLDR-DB.md](../../README-CLDR-DB.md) file.

## Running the Test

1. Navigate to the TranslatorTest presenter in your browser: `http://your-app.test/translator-test`
2. The page will display a table with different count values and the expected output for each.
3. Verify that the "Result" column matches the "Expected Form" column for each count value.

## Expected Results for Czech

For the `room_count` translation key in Czech, you should see:

- For count = 1: "1 pokoj" (one form)
- For count = 2, 3, 4: "X pokoje" (few form)
- For decimal numbers (1.5, 2.5): "X,Y pokoje" (many form)
- For count = 0, 5, and higher: "X pokojů" (other form)

## Troubleshooting

If you're seeing incorrect plural forms:

1. Make sure you're using `CldrTranslator` and `CldrDbDictionaryFactory` in your configuration.
2. Verify that your database contains the correct CLDR plural forms for Czech:
   ```json
   {
       "one": "{count} pokoj",
       "few": "{count} pokoje",
       "many": "{count, number} pokoje",
       "other": "{count} pokojů"
   }
   ```
3. Check that the PHP intl extension is installed and enabled.
4. Enable debug mode in your config to see more detailed error messages.

## Creating a Simple Test in Your Templates

You can add this simple test to any of your templates to quickly verify that plurals are working:

```latte
<h3>Quick Plural Test</h3>
<ul>
    <li>0: {_'room_count', 0}</li>
    <li>1: {_'room_count', 1}</li>
    <li>2: {_'room_count', 2}</li>
    <li>5: {_'room_count', 5}</li>
</ul>
```

This should output:
```
Quick Plural Test
- 0: 0 pokojů
- 1: 1 pokoj
- 2: 2 pokoje
- 5: 5 pokojů
```

If you're seeing different results, check your configuration and database translations.
