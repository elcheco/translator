<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;

/**
 * Test presenter for demonstrating CLDR plurals in templates
 */
class TranslatorTestPresenter extends Presenter
{
    /**
     * Default action that sets up test data for the template
     */
    public function renderDefault(): void
    {
        // Set up various count values to test different plural forms
        $this->template->counts = [0, 1, 2, 3, 4, 5, 10, 21, 22, 25, 1.5, 2.5];

        // You can also test with specific values
        $this->template->specificCount = 2;
    }
}
