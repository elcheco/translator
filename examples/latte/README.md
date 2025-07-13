# Testing Translator in Latte Templates Without a Presenter

This document explains how to test the translator in Latte templates without using a Presenter, by using the `renderToString` method.

## Requirements

- PHP 8.3 or higher
- PHP extensions: intl, tokenizer
- Latte package: `latte/latte`

## Installation

If you haven't already, install Latte:

```bash
composer require --dev latte/latte
```

## Basic Usage

Here's a simple example of how to test the translator in a Latte template without a Presenter:

```php
<?php

// Create a Latte engine
$latte = new Latte\Engine();

// Set a temporary directory for cache
$latte->setTempDirectory('/path/to/temp');

// Create a translator
$translationsDir = __DIR__ . '/translations';
$cacheDir = __DIR__ . '/temp';
$factory = new ElCheco\Translator\NeonDictionary\NeonDictionaryFactory($translationsDir, $cacheDir);
$translator = new ElCheco\Translator\Cldr\CldrTranslator($factory);
$translator->setLocale('cs_CZ');
$translator->setCldrEnabled(true);

// Create a translator extension
$extension = new Latte\Essential\TranslatorExtension(
    // Use a closure to call the translate method
    function ($message, ...$parameters) use ($translator) {
        return $translator->translate($message, ...$parameters);
    }
);

// Add the extension to Latte
$latte->addExtension($extension);

// Define template parameters
$params = [
    'count' => 2,
    'name' => 'John',
];

// Render the template to a string
$output = $latte->renderToString('template.latte', $params);

// Now you can test the output
echo $output;
```

## Testing Different Plural Forms

To test different plural forms, you can create a template with various count values:

```latte
<h1>Plural Test</h1>

<h2>Room Count Test</h2>
<ul>
    <li>0: {_'room_count', 0}</li>
    <li>1: {_'room_count', 1}</li>
    <li>2: {_'room_count', 2}</li>
    <li>3: {_'room_count', 3}</li>
    <li>4: {_'room_count', 4}</li>
    <li>5: {_'room_count', 5}</li>
    <li>1.5: {_'room_count', 1.5}</li>
</ul>
```

## Integration Testing

For integration testing, you can create a PHPUnit test that renders a template and verifies the output. See the `LatteIntegrationTest` class in the `tests/Integration` directory for a complete example.

Here's a simplified version:

```php
<?php

use ElCheco\Translator\NeonDictionary\NeonDictionaryFactory;
use ElCheco\Translator\Cldr\CldrTranslator;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use PHPUnit\Framework\TestCase;

class LatteTest extends TestCase
{
    public function testCzechPlurals(): void
    {
        // Skip if Latte is not installed
        if (!class_exists('Latte\Engine')) {
            $this->markTestSkipped('Latte is not installed.');
        }
        
        // Create translator
        $factory = new NeonDictionaryFactory(__DIR__ . '/translations', __DIR__ . '/temp');
        $translator = new CldrTranslator($factory);
        $translator->setLocale('cs_CZ');
        
        // Create Latte engine
        $latte = new Engine();
        $latte->setTempDirectory(__DIR__ . '/temp');
        
        // Add translator extension
        $extension = new TranslatorExtension(
            function ($message, ...$parameters) use ($translator) {
                return $translator->translate($message, ...$parameters);
            }
        );
        $latte->addExtension($extension);
        
        // Render template
        $output = $latte->renderToString(__DIR__ . '/template.latte');
        
        // Verify output
        $this->assertStringContainsString('<li>1: 1 pokoj</li>', $output);
        $this->assertStringContainsString('<li>2: 2 pokoje</li>', $output);
        $this->assertStringContainsString('<li>5: 5 pokojů</li>', $output);
    }
}
```

## Using with Database Translations

To test with database translations, replace the `NeonDictionaryFactory` with `CldrDbDictionaryFactory`:

```php
// Create database connection
$connection = new \Dibi\Connection([/* database configuration */]);

// Create dictionary factory
$factory = new ElCheco\Translator\Cldr\CldrDbDictionaryFactory($connection, 'ModuleName', false);

// Create translator
$translator = new ElCheco\Translator\Cldr\CldrTranslator($factory);
$translator->setLocale('cs_CZ');
$translator->setCldrEnabled(true);

// ... rest of the code is the same
```

## Troubleshooting

If you encounter issues:

1. Make sure Latte is installed: `composer require --dev latte/latte`
2. Check that the intl extension is enabled: `php -m | grep intl`
3. Verify that your translations are correctly formatted
4. Enable debug mode on the translator: `$translator->setDebugMode(true)`
