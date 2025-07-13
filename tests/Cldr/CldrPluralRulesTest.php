<?php

namespace ElCheco\Translator\Tests\Cldr;

use PHPUnit\Framework\TestCase;
use ElCheco\Translator\Cldr\CldrPluralRules;

class CldrPluralRulesTest extends TestCase
{
    public function testCzechDecimalPluralRules(): void
    {
        // Test Czech decimals use 'many' category
        $this->assertEquals('many', CldrPluralRules::getPluralCategory('cs_CZ', 1.5));
    }
}
