<?php declare(strict_types=1);

namespace Rostenkowski\Translate;


use Mockery;
use Tester\Environment;
use const TEMP_DIR;
use function lcg_value;

$dir = dirname(__DIR__);

require "$dir/vendor/autoload.php";

// Add in bootstrap.php or at the beginning of your test
if (!class_exists('PhpToken')) {
    class PhpToken {
        public $id;
        public $text;
        public $line;
        public $pos;

        public function __construct($id, $text, $line = -1, $pos = -1) {
            $this->id = $id;
            $this->text = $text;
            $this->line = $line;
            $this->pos = $pos;
        }

        public static function tokenize($code, $flags = 0) {
            $tokens = token_get_all($code, $flags);
            $result = [];

            foreach ($tokens as $token) {
                if (is_array($token)) {
                    $result[] = new self($token[0], $token[1], $token[2] ?? -1, -1);
                } else {
                    $result[] = new self(ord($token), $token, -1, -1);
                }
            }

            return $result;
        }
    }
}

// With this:
if (class_exists('\\Random\\Randomizer')) {
    $randomizer = new \Random\Randomizer();
    define('TEMP_DIR', __DIR__ . '/temp/' . (string) $randomizer->getFloat(0, 1));
} else {
    define('TEMP_DIR', __DIR__ . '/temp/' . (string) lcg_value());
}

@mkdir(TEMP_DIR, 0775, true);

Environment::setup();

Mockery::globalHelpers();
