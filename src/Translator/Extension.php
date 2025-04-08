<?php

declare(strict_types=1);

namespace ElCheco\Translator;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use ElCheco\Translator\NeonDictionary\NeonDictionaryFactory;
use ElCheco\Translator\DbDictionary\DbDictionaryFactory;
use ElCheco\Translator\DbDictionary\DbDictionary;
use ElCheco\Translator\ShutdownHandler;

class Extension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'default' => Expect::string('en_US'),
            'fallback' => Expect::string('en_US'),
            'debugMode' => Expect::bool(false),
            'dictionary' => Expect::structure([
                'factory' => Expect::string(NeonDictionaryFactory::class),
                'args' => Expect::anyOf(
                    Expect::array(),
                    Expect::string(),
                    Expect::int(),
                    Expect::float(),
                    Expect::bool(),
                    Expect::null()
                ),
            ])->castTo('array'),
            'commands' => Expect::listOf(
                Expect::anyOf(Expect::string(), Expect::type(Statement::class))
            )->default([]),
        ])->castTo('array');
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $config = $this->getConfig();

        // Add dictionary factory
        $factoryClass = $config['dictionary']['factory'];
        $factoryArgs = $config['dictionary']['args'] ?? [];

        // Handle different factory types and their arguments
        if ($factoryClass === NeonDictionaryFactory::class) {
            // Default structure for NeonDictionaryFactory
            if (is_array($factoryArgs) && isset($factoryArgs['directory'])) {
                // Associative array with named keys
                $directory = $factoryArgs['directory'] ?? '%appDir%/translations';
                $cacheDir = $factoryArgs['cache'] ?? '%tempDir%/cache/translations';
                $autoRefresh = $factoryArgs['autoRefresh'] ?? true;

                // Create factory definition with proper arguments
                $builder->addDefinition($this->prefix('dictionaryFactory'))
                    ->setFactory($factoryClass, [
                        $directory,
                        $cacheDir,
                        $autoRefresh
                    ]);
            } else {
                // Simple setup with default values or indexed array
                $args = is_array($factoryArgs) && !empty($factoryArgs) ? $factoryArgs : [
                    '%appDir%/translations',
                    '%tempDir%/cache/translations',
                    true
                ];
                $builder->addDefinition($this->prefix('dictionaryFactory'))
                    ->setFactory($factoryClass, $args);
            }
        } elseif ($factoryClass === DbDictionaryFactory::class) {
            // Handle DbDictionaryFactory - always use Statement for arrays
            if (is_array($factoryArgs)) {
                // If arguments are an array, use Statement to pass them
                $statement = new Statement($factoryClass, $factoryArgs);
                $builder->addDefinition($this->prefix('dictionaryFactory'))
                    ->setFactory($statement);
            } else {
                // If not an array, pass as is (though this probably won't work correctly)
                $builder->addDefinition($this->prefix('dictionaryFactory'))
                    ->setFactory($factoryClass, $factoryArgs ? [$factoryArgs] : []);
            }
        } else {
            // For custom factories
            if (is_array($factoryArgs)) {
                $statement = new Statement($factoryClass, $factoryArgs);
                $builder->addDefinition($this->prefix('dictionaryFactory'))
                    ->setFactory($statement);
            } else {
                $args = $factoryArgs !== null ? [$factoryArgs] : [];
                $builder->addDefinition($this->prefix('dictionaryFactory'))
                    ->setFactory($factoryClass, $args);
            }
        }

        // Add translator
        $builder->addDefinition($this->prefix('translator'))
            ->setFactory(Translator::class, [
                $this->prefix('@dictionaryFactory'),
                null, // logger will be set later if available
                $config['debugMode']
            ])
            ->addSetup('setFallbackLocale', [$config['fallback'] ?? $config['default']])
            ->addSetup('setLocale', [$config['default']])
            ->setAutowired(true);

        // Add shutdown handler if using DbDictionary
        if ($factoryClass === DbDictionaryFactory::class) {
            $builder->addDefinition($this->prefix('shutdownHandler'))
                ->setFactory(ShutdownHandler::class, [
                    $this->prefix('@translator')
                ])
                ->setAutowired(false);
        }

        // Register commands if specified
        foreach ($config['commands'] as $index => $command) {
            if ($command instanceof Statement) {
                // Already a statement, use as is
                $builder->addDefinition($this->prefix('command.' . $index))
                    ->setFactory($command)
                    ->addTag('console.command');
            } elseif (is_string($command)) {
                // Command class name without arguments
                $builder->addDefinition($this->prefix('command.' . $index))
                    ->setFactory($command)
                    ->addTag('console.command');
            }
        }
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();
        $config = $this->getConfig();

        // Set logger if available
        if ($builder->hasDefinition('logger')) {
            $builder->getDefinition($this->prefix('translator'))
                ->addSetup('setLogger', ['@logger']);
        }

        // Setup shutdown handler for DbDictionary
        if ($config['dictionary']['factory'] === DbDictionaryFactory::class
            && $builder->hasDefinition('application')) {

            $builder->getDefinition('application')
                ->addSetup('$service->onShutdown[] = ?', [
                    [$this->prefix('@shutdownHandler'), 'onApplicationShutdown']
                ]);
        }
    }
}
