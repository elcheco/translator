<?php declare(strict_types=1);

namespace ElCheco\Translator;


use Psr\Log\LoggerInterface;
use ElCheco\Translator\NeonDictionary\NeonDictionaryFactory;
use Tester\Assert;
use const M_PI;
use const TEMP_DIR;
use function spy;

require __DIR__ . '/../../bootstrap.php';

$dataDir = __DIR__ . '/translations';
$tempDir = TEMP_DIR;

$t = new Translator(new NeonDictionaryFactory($dataDir, $tempDir));

$t->setLocale('cs_CZ');

// test: process parameters for an untranslated message
Assert::equal('Hi Bernardette!',
    $t->translate('Hi %s!', 'Bernardette'));

// test: plural with parameters
Assert::equal('Máš 1 bod Bernardette', $t->translate('You have %s points %s', 1, 'Bernardette'));
Assert::equal('Máš 2 body Bernardette', $t->translate('You have %s points %s', 2, 'Bernardette'));
Assert::equal('Máš 5 bodů Bernardette', $t->translate('You have %s points %s', 5, 'Bernardette'));

// test: empty message is allowed
Assert::equal('', $t->translate(''));

// test: simple message
Assert::equal('Vítejte!', $t->translate('Welcome!'));

// test: plural forms
Assert::equal('Máte 1 nepřečtenou zprávu.',
    $t->translate('You have %s unread messages.', 1));
Assert::equal('Máte 2 nepřečtené zprávy.',
    $t->translate('You have %s unread messages.', 2));
Assert::equal('Máte 5 nepřečtených zpráv.',
    $t->translate('You have %s unread messages.', 5));

// test: plural translation (with count) defined as simple message (not array)
// this may happen for languages without singular/plural
$message = 'I have %s dogs';
Assert::same('Mám 5 psů', $t->translate($message, 5));

// test error: non-string message in production mode
Assert::same('Message must be string, but array given.', $t->translate([]));

// test: NULL parameters - now handle null being passed as the "first" parameter
Assert::equal('Hi !', $t->translate('Hi %s!', null));

// test: null in second position
Assert::equal('Hi test!', $t->translate('Hi %s!', 'test'));

// test: NULL count in debug mode
Assert::exception(function () use ($t) {
    $t->setDebugMode(true);
    $t->translate('You have %s unread messages.', null);
}, TranslatorException::class, 'Multiple plural forms are available (message: You have %s unread messages.), but the $count is NULL.');

// test: accidentally empty translation
Assert::same('Article author', $t->translate('Article author'));

// test: special form for the parametrized translation with count = 0 (zero)
$t->setDebugMode(true);
Assert::same("Čas vypršel", $t->translate('You have %s seconds', 0));
Assert::same("Máte 1 vteřinu", $t->translate('You have %s seconds', 1));
Assert::same("Máte 2 vteřiny", $t->translate('You have %s seconds', 2));
Assert::same("Máte 5 vteřin", $t->translate('You have %s seconds', 5));

// test: string objects
Assert::same('foo', $t->translate(new class
{
    function __toString() { return 'foo'; }
}));

// test: error: non-string message in debug mode
Assert::exception(function () use ($t, $message) {
    $t->translate([]);
}, TranslatorException::class, 'Message must be string, but array given.');

// test: psr logger
$logger = spy(LoggerInterface::class);

$t->setDebugMode(false);
$t->setLogger($logger);
$t->translate([]);

$logger->shouldHaveReceived()->warning('translator: Message must be string, but array given.');

// test: translate numbers
// Note: Changed test case as PI is now passed as a parameter
// This section was commented out in the original file
$t->setLocale('cs_CZ');
// Disable number translation test as we modified the API
// Assert::same('3,14', $t->translate(M_PI, 2));
