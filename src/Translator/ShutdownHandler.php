<?php

declare(strict_types=1);

namespace ElCheco\Translator;

use Nette\Application\Application;
use ElCheco\Translator\DbDictionary\DbDictionary;
use ElCheco\Translator\Cldr\CldrDbDictionary;

/**
 * Service class for handling translator shutdown events
 */
class ShutdownHandler
{
    private Translator $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Event handler to save usage stats when the application shuts down.
     */
    public function onApplicationShutdown(Application $application): void
    {
        $dictionary = $this->translator->getDictionary();
        if ($dictionary instanceof DbDictionary) {
            $dictionary->saveUsageStats();
        } elseif ($dictionary instanceof CldrDbDictionary) {
            // Access the wrapped DbDictionary using reflection
            $reflection = new \ReflectionClass($dictionary);
            $dbDictionaryProperty = $reflection->getProperty('dbDictionary');
            $dbDictionaryProperty->setAccessible(true);
            $dbDictionary = $dbDictionaryProperty->getValue($dictionary);
            
            if ($dbDictionary instanceof DbDictionary) {
                $dbDictionary->saveUsageStats();
            }
        }
    }
}
