<?php

declare(strict_types=1);

namespace ElCheco\Translator\Console;

use Dibi\Connection;
use Nette\Neon\Neon;
use Symfony\Component\Console;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function strip_tags;

#[Console\Attribute\AsCommand(
    name: 'translations:import-neon',
    description: 'Import translations from NEON files into the database',
    hidden: false
)]
class ImportNeonTranslationsCommand extends Console\Command\Command
{
    private Connection $connection;
    protected InputInterface $input;
    protected OutputInterface $output;

    public static function getDefaultName(): ?string
    {
        return 'translations:import-neon';
    }

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->setName('translations:import-neon')
            ->setDescription('Import translations from NEON files into the database')
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory containing NEON translation files')
            ->addArgument('module', InputArgument::REQUIRED, 'Translation module name to import into')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Specific locale to import')
            ->addOption('mark-as-translated', 't', InputOption::VALUE_NONE, 'Mark imported translations as translated')
            ->addOption('mark-as-approved', 'a', InputOption::VALUE_NONE, 'Mark imported translations as approved')
            ->addOption('overwrite', 'o', InputOption::VALUE_NONE, 'Overwrite existing translations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Importing NEON translations into database');

        $directory = rtrim($input->getArgument('directory'), '/\\');
        $moduleName = $input->getArgument('module');
        $locale = $input->getOption('locale');
        $markAsTranslated = $input->getOption('mark-as-translated');
        $markAsApproved = $input->getOption('mark-as-approved');
        $overwrite = $input->getOption('overwrite');

        // Check if directory exists
        if (!is_dir($directory)) {
            $io->error("Directory not found: $directory");
            return Console\Command\Command::FAILURE;
        }

        // Get or create module
        $moduleId = $this->getOrCreateModule($moduleName, $io);
        if (!$moduleId) {
            return Console\Command\Command::FAILURE;
        }

        // Find NEON files
        $files = [];
        if ($locale) {
            $filePath = "$directory/$locale.neon";
            if (file_exists($filePath)) {
                $files[$locale] = $filePath;
            } else {
                $io->error("Locale file not found: $filePath");
                return Console\Command\Command::FAILURE;
            }
        } else {
            foreach (glob("$directory/*.neon") as $file) {
                $localeCode = basename($file, '.neon');
                $files[$localeCode] = $file;
            }
        }

        if (empty($files)) {
            $io->error("No NEON files found in $directory");
            return Console\Command\Command::FAILURE;
        }

        $io->section('Found the following translation files:');
        foreach ($files as $localeCode => $filePath) {
            $io->writeln("- $localeCode: " . basename($filePath));
        }

        $totalImported = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($files as $localeCode => $filePath) {
            $io->section("Processing $localeCode translations");

            try {
                $content = file_get_contents($filePath);
                $translations = Neon::decode($content);

                if (!is_array($translations)) {
                    $io->warning("No translations found in $filePath");
                    continue;
                }

                $imported = 0;
                $updated = 0;
                $skipped = 0;

                $this->connection->begin();

                try {
                    foreach ($translations as $key => $value) {
                        $result = $this->processTranslation(
                            $moduleId,
                            $key,
                            $value,
                            $localeCode,
                            $markAsTranslated,
                            $markAsApproved,
                            $overwrite
                        );

                        if ($result === 'imported') $imported++;
                        elseif ($result === 'updated') $updated++;
                        elseif ($result === 'skipped') $skipped++;
                    }

                    $this->connection->commit();
                } catch (\Exception $e) {
                    $this->connection->rollback();
                    $io->error("Error processing $localeCode: " . $e->getMessage());
                    continue;
                }

                $io->success("Processed $localeCode: imported $imported, updated $updated, skipped $skipped");
                $totalImported += $imported;
                $totalUpdated += $updated;
                $totalSkipped += $skipped;
            } catch (\Exception $e) {
                $io->error("Error reading $filePath: " . $e->getMessage());
            }
        }

        $io->section('Import Summary');
        $io->definitionList(
            ['Module' => $moduleName],
            ['Total Imported' => $totalImported],
            ['Total Updated' => $totalUpdated],
            ['Total Skipped' => $totalSkipped]
        );

        return Console\Command\Command::SUCCESS;
    }

    /**
     * Detects if a string contains HTML tags
     */
    private function containsHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }

    /**
     * Process a single translation entry
     *
     * @return string 'imported', 'updated', or 'skipped'
     * @throws Exception
     */
    private function processTranslation(
        int $moduleId,
        string $key,
        $value,
        string $locale,
        bool $markAsTranslated,
        bool $markAsApproved,
        bool $overwrite
    ): string {
        // Determine translation type and process value
        $type = 'text';
        $textValue = null;
        $pluralValues = null;

        if (is_array($value)) {
            $type = 'plural';
            $pluralValues = $value;
        } else {
            $textValue = (string)$value;
            if ($this->containsHtml($textValue)) {
                $type = 'html';
            }
        }

        // Check if key exists
        $keyRecord = $this->connection->query('
            SELECT [id] FROM [translation_keys]
            WHERE [module_id] = %i AND [key] = %s
            LIMIT 1
        ', $moduleId, $key)->fetch();

        // Create or update key record
        if (!$keyRecord) {
            $this->connection->query('
                INSERT INTO [translation_keys]
                ([module_id], [key], [type])
                VALUES (%i, %s, %s)
            ', $moduleId, $key, $type);
            $keyId = (int)$this->connection->getInsertId();
        } else {
            $keyId = (int)$keyRecord['id'];

            // Update key type if needed
            $this->connection->query('
                UPDATE [translation_keys]
                SET [type] = %s
                WHERE [id] = %i
            ', $type, $keyId);
        }

        // Check if translation exists
        $translationRecord = $this->connection->query('
            SELECT [id] FROM [translations]
            WHERE [key_id] = %i AND [locale] = %s
            LIMIT 1
        ', $keyId, $locale)->fetch();

        // Skip if existing and not overwriting
        if ($translationRecord && !$overwrite) {
            return 'skipped';
        }

        // Prepare the values
        $values = [
            'key_id' => $keyId,
            'locale' => $locale,
            'value' => $type !== 'plural' ? $textValue : null,
            'plural_values' => $type === 'plural' ? json_encode($pluralValues) : null,
            'is_translated' => $markAsTranslated,
            'is_approved' => $markAsApproved,
        ];

        // Insert or update translation
        if (!$translationRecord) {
            $this->connection->query('
                INSERT INTO [translations] %v
            ', $values);
            return 'imported';
        } else {
            $this->connection->query('
                UPDATE [translations] SET %a
                WHERE [id] = %i
            ', $values, $translationRecord['id']);
            return 'updated';
        }
    }

    /**
     * Get or create a module by name
     *
     * @return int|null Module ID
     * @throws Exception
     */
    private function getOrCreateModule(string $moduleName, SymfonyStyle $io): ?int
    {
        $moduleRecord = $this->connection->query('
            SELECT [id], [is_active] FROM [translation_modules]
            WHERE [name] = %s
            LIMIT 1
        ', $moduleName)->fetch();

        if ($moduleRecord) {
            if (!$moduleRecord['is_active']) {
                $io->warning("Module '$moduleName' exists but is inactive. Activating it.");
                $this->connection->query('
                    UPDATE [translation_modules]
                    SET [is_active] = 1
                    WHERE [id] = %i
                ', $moduleRecord['id']);
            }
            return (int)$moduleRecord['id'];
        }

        $io->note("Creating new module: $moduleName");
        $this->connection->query('
            INSERT INTO [translation_modules]
            ([name], [is_active])
            VALUES (%s, 1)
        ', $moduleName);

        return (int)$this->connection->getInsertId();
    }
}
