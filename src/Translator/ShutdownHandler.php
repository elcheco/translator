<?php

declare(strict_types=1);

namespace ElCheco\Translator;

use Nette\Application\Application;
use ElCheco\Translator\DbDictionary\DbDictionary;

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
        }
    }
}
