<?php
/**
 * @author    Miroslav Koula
 * @copyright Copyright (c) 2018 Miroslav Koula, https://elcheco.it
 * @created  01/10/18 14:02
 */

declare(strict_types=1);

namespace ElCheco\Translator;

class Translation
{
    /**
     * @var string
     */
    protected $translation;

    /**
     * @var int
     */
    protected $max;

    public function __construct(array $translation)
    {
        $this->translation = $translation;
        $this->max = \max(\array_keys($translation));
    }

    public function get(int $count)
    {
        if (isset($this->translation[$count])) {
            return $this->translation[$count];
        } else {
            return $this->translation[$this->max];
        }
    }

}
