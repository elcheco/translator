<?php

declare(strict_types=1);

// This script demonstrates how to test the translator in Latte templates
// without using a Presenter, by using the renderToString method.

// Check if Latte is installed
if (!class_exists('Latte\Engine')) {
    echo "Error: Latte is not installed. Run 'composer require latte/latte' to install it.\n";
    exit(1);
}

// Check if intl extension is available
if (!extension_loaded('intl')) {
    echo "Warning: The intl extension is not available. Some features may not work correctly.\n";
}

// Set up paths
$baseDir = __DIR__;
$translationsDir = $baseDir . '/translations';
$templatesDir = $baseDir . '/templates';
$tempDir = $baseDir . '/temp';

// Create temp directory if it doesn't exist
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Require autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Create a Latte engine
$latte = new Latte\Engine();

// Set a temporary directory for cache
$latte->setTempDirectory($tempDir);

// Create a translator
$factory = new ElCheco\Translator\NeonDictionary\NeonDictionaryFactory($translationsDir, $tempDir);
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
    'name' => 'World',
];

// Render the template to a string
$output = $latte->renderToString($templatesDir . '/plural_test.latte', $params);

// Output the result
echo $output;

// You can also save the output to a file
file_put_contents($baseDir . '/output.html', $output);
echo "\nOutput saved to " . $baseDir . "/output.html\n";

// Test with different count values
echo "\nTesting with different count values:\n";
$testCounts = [0, 1, 2, 3, 4, 5, 1.5];

foreach ($testCounts as $count) {
    $params['count'] = $count;
    $result = $translator->translate('room_count', $count);
    echo "Count $count: $result\n";
}
