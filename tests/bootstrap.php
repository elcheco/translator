<?php

declare(strict_types=1);

/**
 * Bootstrap file for ElCheco Translator tests
 */

// Composer autoloader
$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$autoloadFound = false;
foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    die('Composer autoloader not found. Please run "composer install".' . PHP_EOL);
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Set timezone
date_default_timezone_set('UTC');

// Create temp directory for tests
$tempDir = sys_get_temp_dir() . '/elcheco_translator_tests';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Register test cleanup
register_shutdown_function(function() use ($tempDir) {
    if (is_dir($tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }

        @rmdir($tempDir);
    }
});

// Set up Mockery if available
if (class_exists('Mockery')) {
    Mockery::globalHelpers();
}
