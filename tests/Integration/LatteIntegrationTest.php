<?php

declare(strict_types=1);

namespace ElCheco\Translator\Tests\Integration;

use ElCheco\Translator\NeonDictionary\NeonDictionaryFactory;
use ElCheco\Translator\Cldr\CldrTranslator;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for Latte templates with the translator
 *
 * This test demonstrates how to test the translator in Latte templates
 * without using a Presenter, by using the renderToString method.
 *
 * Note: This test requires the Latte package, which is not included in the project
 * dependencies by default. To run this test, you need to install Latte:
 *
 * ```
 * composer require --dev latte/latte
 * ```
 *
 * The test will be skipped if Latte is not installed.
 */
class LatteIntegrationTest extends TestCase
{
    private string $translationsDir;
    private string $templatesDir;
    private string $cacheDir;
    private Engine $latte;
    private CldrTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if intl extension is not available
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The Intl extension is not available.');
        }

        // Skip if Latte is not installed
        if (!class_exists('Latte\Engine')) {
            $this->markTestSkipped('Latte is not installed. Run "composer require latte/latte" to install it.');
        }

        $this->translationsDir = __DIR__ . '/../fixtures/translations';
        $this->templatesDir = __DIR__ . '/../fixtures/templates';
        $this->cacheDir = sys_get_temp_dir() . '/translator_tests_' . uniqid();

        // Create cache directory
        mkdir($this->cacheDir, 0777, true);

        // Set up translator
        $this->setupTranslator();

        // Set up Latte engine
        $this->setupLatte();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up cache directory
        $this->removeDirectory($this->cacheDir);
    }

    /**
     * Test Czech plural forms in Latte templates
     */
    public function testCzechPluralsInLatte(): void
    {
        // Set translator locale to Czech
        $this->translator->setLocale('cs_CZ');

        // Render the template
        $output = $this->latte->renderToString($this->templatesDir . '/plural_test.latte');

        // Verify room_count translations
        $this->assertStringContainsString('<li>0: 0 pokojů</li>', $output);
        $this->assertStringContainsString('<li>1: 1 pokoj</li>', $output);
        $this->assertStringContainsString('<li>2: 2 pokoje</li>', $output);
        $this->assertStringContainsString('<li>3: 3 pokoje</li>', $output);
        $this->assertStringContainsString('<li>4: 4 pokoje</li>', $output);
        $this->assertStringContainsString('<li>5: 5 pokojů</li>', $output);
        $this->assertStringContainsString('<li>1.5: 1,5 pokoje</li>', $output);

        // Verify days_count translations
        $this->assertStringContainsString('<li>0: 0 dní</li>', $output);
        $this->assertStringContainsString('<li>1: 1 den</li>', $output);
        $this->assertStringContainsString('<li>2: 2 dny</li>', $output);
        $this->assertStringContainsString('<li>5: 5 dní</li>', $output);

        // Verify legacy messages_count translations
        $this->assertStringContainsString('<li>0: 0 zpráv</li>', $output);
        $this->assertStringContainsString('<li>1: 1 zpráva</li>', $output);
        $this->assertStringContainsString('<li>2: 2 zprávy</li>', $output);
        $this->assertStringContainsString('<li>5: 5 zpráv</li>', $output);

        // Verify simple translations
        $this->assertStringContainsString('<p>Vítejte na našem webu</p>', $output);
        $this->assertStringContainsString('<p>Ahoj World</p>', $output);

        $output = $this->latte->renderToString($this->templatesDir . '/room_1.latte');
        $this->assertEquals('1 pokoj', $output);

        $output = $this->latte->renderToString($this->templatesDir . '/room_2.latte');
        $this->assertEquals('2 pokoje', $output);

        $output = $this->latte->renderToString($this->templatesDir . '/room_5.latte');
        $this->assertEquals('5 pokojů', $output);

        $output = $this->latte->renderToString($this->templatesDir . '/room_1_simple.latte');
        $this->assertEquals('pokoj', $output);

        $output = $this->latte->renderToString($this->templatesDir . '/room_2_simple.latte');
        $this->assertEquals('pokoje', $output);

        $output = $this->latte->renderToString($this->templatesDir . '/room_5_simple.latte');
        $this->assertEquals('pokojů', $output);
    }

    /**
     * Test English plural forms in Latte templates
     */
    public function testEnglishPluralsInLatte(): void
    {
        // Set translator locale to English
        $this->translator->setLocale('en_US');

        // Render the template
        $output = $this->latte->renderToString($this->templatesDir . '/plural_test.latte');

        // Verify room_count translations (English has simpler plural rules)
        $this->assertStringContainsString('<li>0: 0 rooms</li>', $output);
        $this->assertStringContainsString('<li>1: 1 room</li>', $output);
        $this->assertStringContainsString('<li>2: 2 rooms</li>', $output);

        // Verify messages_count_legacy translations
        $this->assertStringContainsString('<li>0: You have 0 messages</li>', $output);
        $this->assertStringContainsString('<li>1: You have 1 message</li>', $output);
        $this->assertStringContainsString('<li>2: You have 2 messages</li>', $output);

        // Verify simple translations
        $this->assertStringContainsString('<p>Welcome to our website</p>', $output);
        $this->assertStringContainsString('<p>Hello World</p>', $output);
    }

    /**
     * Set up the translator
     */
    private function setupTranslator(): void
    {
        // Create a custom factory that returns CldrNeonDictionary instances
        $factory = new class($this->translationsDir, $this->cacheDir) implements \ElCheco\Translator\DictionaryFactoryInterface {
            private string $directory;
            private string $cacheDir;

            public function __construct(string $directory, string $cacheDir) {
                $this->directory = $directory;
                $this->cacheDir = $cacheDir;
            }

            public function create(string $locale, ?string $fallbackLocale = null): \ElCheco\Translator\DictionaryInterface {
                $sourceFile = "{$this->directory}/{$locale}.neon";
                $cacheFile = "{$this->cacheDir}/{$locale}.php";

                $fallbackSourceFile = $fallbackLocale ? "{$this->directory}/{$fallbackLocale}.neon" : null;

                return new \ElCheco\Translator\Cldr\CldrNeonDictionary($sourceFile, $cacheFile, $fallbackSourceFile);
            }
        };

        // Create CLDR translator
        $this->translator = new CldrTranslator($factory);
        $this->translator->setLocale('cs_CZ'); // Default locale
        $this->translator->setCldrEnabled(true);
    }

    /**
     * Set up the Latte engine
     */
    private function setupLatte(): void
    {
        $this->latte = new Engine();

        // Set cache directory
        $this->latte->setTempDirectory($this->cacheDir);

        // Create translator extension
        $extension = new TranslatorExtension(
            // Use a closure to call the translate method
            function ($message, ...$parameters) {
                return $this->translator->translate($message, ...$parameters);
            }
        );

        // Add translator extension to Latte
        $this->latte->addExtension($extension);
    }

    /**
     * Remove a directory and its contents recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
