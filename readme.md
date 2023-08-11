Translator 
--
[![Downloads this Month](https://img.shields.io/packagist/dm/elcheco/translator.svg)](https://packagist.org/packages/elcheco/translator)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/elcheco/translator/blob/master/LICENSE)

Lightweight and powerful translation system for PHP 8.0+, build as component 
not only for [Nette](https://nette.org) framework

Note: 
Inspired by [rostenkowski/translate](https://github.com/rostenkowski/translate), but I needed support for Nette Framework ^3.2|^4.0
and fallback translation possibility. I also refactored a bit the plurals to be naturally understandable. 

## Install
```bash
composer require elcheco/translator
```

## Translations 

Translations are stored in *.neon files in this format:  

```yml
# simple message
Hi!: Ahoj!

# supporting placeholder
Hi %s!: Ahoj %s! 

# supporting also plurals in multiple forms

# in English it's easy to use
You have %s points.: 
  0: You have no points
  1: You have %s point.
  2: You have %s points.

# but for example in Czech plurals are a bit more complicated  
You have %s points.: 
  0: Nemáte žádné body.
  1: Máte %s bod.
  "2-4": Máte %s body.
  5: Máte %s bodů.
```


### Usage with Nette Framework

Put your translations to `%appDir%/translations` directory as `cs_CZ.neon` etc.

```yml
# register extension
extensions:
  translate: ElCheco\Translator\Extension
  
# configuration
translator:
  default: en_US
  fallback: cs_CZ
```

### Usage with plain PHP

```php
<?php

namespace App;

require __DIR__ . '/vendor/autoload.php';

use ElCheco\Translator\Translator;
use ElCheco\Translator\NeonDictionary\NeonDictionaryFactory;

// both translations and cache are in the same directory
$translator = new Translator(new NeonDictionaryFactory(__DIR__, __DIR__));
$translator->setLocale('cs_CZ');
$translator->translate('Welcome!');
```

## Requirements

- PHP 8.0+
- nette/di
- nette/neon
- nette/safe-stream
- nette/utils
- nette/tester

