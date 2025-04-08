<?php declare(strict_types=1);

namespace ElCheco\Translator;


use Nette\DI\Compiler;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nette\Localization\Translator;
use Nette\Utils\Finder;
use const TEMP_DIR;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

echo "Running PHP version: " . PHP_VERSION . "\n";

$tempDir = TEMP_DIR;

// create container
$loader = new  ContainerLoader($tempDir, true);
$class = $loader->load(function (Compiler $compiler) use ($tempDir) {

	// use extensions extension to load our extension
	$compiler->addExtension('extensions', new ExtensionsExtension());

    $compiler->addConfig(['parameters' => [
        'appDir'  => __DIR__,
        'tempDir' => TEMP_DIR,
    ]]);

	// load our config file
	$compiler->loadConfig(__DIR__ . '/config.neon');
});

$container = new $class;
/** @var Translator $translator */
$translator = $container->getByType(Translator::class);

// czech locale is set in test config
Assert::equal('Vítejte!', $translator->translate('Welcome!'));
