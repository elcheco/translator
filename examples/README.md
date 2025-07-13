# ElCheco Translator Examples

This directory contains examples and tools for using and testing the ElCheco Translator in different environments.

## Available Examples

### Latte Integration

The `latte` directory contains examples for using the translator in Latte templates without a Presenter:

- `render_test.php`: A script that demonstrates how to use the translator with Latte's renderToString method
- `templates/plural_test.latte`: A template that demonstrates various plural forms
- `translations/cs_CZ.neon`: A Czech translation file with CLDR plural forms
- `README.md`: Detailed documentation on testing the translator in Latte templates

To run the Latte example:

1. Install Latte: `composer require --dev latte/latte`
2. Run the script: `php examples/latte/render_test.php`

### Nette Integration

The `nette` directory contains examples for using the translator in a Nette application:

- `TranslatorTestPresenter.php`: A presenter for testing the translator
- `templates/TranslatorTest/default.latte`: A template for testing the translator
- `README.md`: Instructions for setting up and using the test presenter

### Command-Line Testing

The `bin` directory contains a standalone script for testing CLDR plurals from the command line:

- `test-translator.php`: A script that sets up an in-memory database and tests the translator
- `README.md`: Instructions for using the script and interpreting the results

## Quick Start

The fastest way to test the translator is to use the Latte example:

```bash
# Install Latte
composer require --dev latte/latte

# Run the example
php examples/latte/render_test.php
```

This will render a template with various plural forms and output the result to the console and to a file.

## Requirements

- PHP 8.3 or higher
- PHP extensions: intl, tokenizer
- Composer dependencies installed

## Troubleshooting

If you encounter issues:

1. Make sure you have the required PHP extensions installed:
   ```bash
   php -m | grep -E 'intl|tokenizer'
   ```

2. Make sure you've installed the dependencies:
   ```bash
   composer install
   ```

3. For Latte examples, make sure Latte is installed:
   ```bash
   composer require --dev latte/latte
   ```

4. Check for PHP errors in the output.
