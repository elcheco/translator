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
use ElCheco\Translator\Cldr\CldrPluralRules;

#[Console\Attribute\AsCommand(
    name: 'translations:convert-to-cldr',
    description: 'Convert legacy plural translations to CLDR format',
    hidden: false
)]
class ConvertToCldrCommand extends Console\Command\Command
{
    private Connection $connection;

    public static function getDefaultName(): ?string
    {
        return 'translations:convert-to-cldr';
    }

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->setName('translations:convert-to-cldr')
            ->setDescription('Convert legacy plural translations to CLDR format')
            ->addArgument('source', InputArgument::REQUIRED, 'Source: "neon" for NEON files or "database" for DB')
            ->addArgument('path', InputArgument::REQUIRED, 'Directory path for NEON files or module name for DB')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Specific locale to convert')
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Output directory for converted files', './translations/cldr')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would be converted without making changes')
            ->addOption('backup', 'b', InputOption::VALUE_NONE, 'Create backup of original files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Converting Legacy Translations to CLDR Format');

        $source = $input->getArgument('source');
        $path = $input->getArgument('path');
        $locale = $input->getOption('locale');
        $outputDir = $input->getOption('output-dir');
        $dryRun = $input->getOption('dry-run');
        $backup = $input->getOption('backup');

        if ($dryRun) {
            $io->note('Running in dry-run mode - no changes will be made');
        }

        try {
            if ($source === 'neon') {
                return $this->convertNeonFiles($io, $path, $locale, $outputDir, $dryRun, $backup);
            } elseif ($source === 'database') {
                return $this->convertDatabase($io, $path, $locale, $dryRun);
            } else {
                $io->error('Invalid source. Use "neon" or "database".');
                return Console\Command\Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Conversion failed: ' . $e->getMessage());
            return Console\Command\Command::FAILURE;
        }
    }

    /**
     * Convert NEON files to CLDR format
     */
    private function convertNeonFiles(
        SymfonyStyle $io,
        string $directory,
        ?string $locale,
        string $outputDir,
        bool $dryRun,
        bool $backup
    ): int {
        if (!is_dir($directory)) {
            $io->error("Directory not found: $directory");
            return Console\Command\Command::FAILURE;
        }

        // Create output directory
        if (!$dryRun && !is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                $io->error("Failed to create output directory: $outputDir");
                return Console\Command\Command::FAILURE;
            }
        }

        // Find NEON files
        $pattern = $locale ? "$directory/$locale.neon" : "$directory/*.neon";
        $files = glob($pattern);

        if (empty($files)) {
            $io->warning("No NEON files found");
            return Console\Command\Command::SUCCESS;
        }

        $totalConverted = 0;

        foreach ($files as $file) {
            $localeCode = basename($file, '.neon');
            $io->section("Processing $localeCode");

            try {
                $content = file_get_contents($file);
                $translations = Neon::decode($content);

                if (!is_array($translations)) {
                    $io->warning("No translations found in $file");
                    continue;
                }

                $converted = $this->convertTranslations($translations, $localeCode);
                $conversionCount = $converted['count'];

                if ($conversionCount > 0) {
                    $io->info("Found $conversionCount plural translations to convert");

                    if (!$dryRun) {
                        // Backup original if requested
                        if ($backup) {
                            $backupFile = $file . '.backup';
                            copy($file, $backupFile);
                            $io->note("Backup created: $backupFile");
                        }

                        // Save converted file
                        $outputFile = "$outputDir/$localeCode.neon";
                        $neonContent = Neon::encode($converted['translations'], true);
                        file_put_contents($outputFile, $neonContent);
                        $io->success("Converted file saved: $outputFile");
                    }

                    // Show examples
                    $this->showConversionExamples($io, $converted['examples'], 3);

                    $totalConverted += $conversionCount;
                } else {
                    $io->info("No plural translations found to convert");
                }
            } catch (\Exception $e) {
                $io->error("Error processing $file: " . $e->getMessage());
            }
        }

        $io->success("Total translations converted: $totalConverted");
        return Console\Command\Command::SUCCESS;
    }

    /**
     * Convert database translations to CLDR format
     */
    private function convertDatabase(
        SymfonyStyle $io,
        string $moduleName,
        ?string $locale,
        bool $dryRun
    ): int {
        // Get module ID
        $moduleId = $this->getModuleId($moduleName);
        if (!$moduleId) {
            $io->error("Module '$moduleName' not found");
            return Console\Command\Command::FAILURE;
        }

        // Build query
        $query = $this->connection->select('k.id, k.key, k.type, t.locale, t.plural_values')
            ->from('[translation_keys] k')
            ->join('[translations] t')->on('k.id = t.key_id')
            ->where('k.module_id = %i', $moduleId)
            ->where('k.type = %s', 'plural')
            ->where('t.plural_values IS NOT NULL');

        if ($locale) {
            $query->where('t.locale = %s', $locale);
        }

        $rows = $query->fetchAll();

        if (empty($rows)) {
            $io->warning("No plural translations found to convert");
            return Console\Command\Command::SUCCESS;
        }

        $io->info("Found " . count($rows) . " plural translations to convert");

        if (!$dryRun) {
            $this->connection->begin();
        }

        $converted = 0;
        $examples = [];

        try {
            foreach ($rows as $row) {
                $legacyPlurals = json_decode($row['plural_values'], true);
                if (!is_array($legacyPlurals)) {
                    continue;
                }

                $cldrForms = $this->convertLegacyToCldr($legacyPlurals, $row['locale']);

                if (!$dryRun) {
                    // Update format type
                    $this->connection->query('
                        UPDATE [translation_keys]
                        SET [format_type] = %s
                        WHERE [id] = %i
                    ', 'icu', $row['id']);

                    // Update plural values with CLDR format
                    $this->connection->query('
                        UPDATE [translations]
                        SET [plural_values] = %s
                        WHERE [key_id] = %i AND [locale] = %s
                    ', json_encode($cldrForms), $row['id'], $row['locale']);
                }

                $converted++;

                // Collect examples
                if (count($examples) < 5) {
                    $examples[] = [
                        'key' => $row['key'],
                        'locale' => $row['locale'],
                        'before' => $legacyPlurals,
                        'after' => $cldrForms
                    ];
                }
            }

            if (!$dryRun) {
                $this->connection->commit();
                $io->success("Successfully converted $converted translations");
            } else {
                $io->info("Would convert $converted translations");
            }

            // Show examples
            $this->showConversionExamples($io, $examples, 5);

        } catch (\Exception $e) {
            if (!$dryRun) {
                $this->connection->rollback();
            }
            throw $e;
        }

        return Console\Command\Command::SUCCESS;
    }

    /**
     * Convert translations array, converting plurals to CLDR format
     */
    private function convertTranslations(array $translations, string $locale): array
    {
        $converted = [];
        $examples = [];
        $count = 0;

        foreach ($translations as $key => $value) {
            if (is_array($value) && $this->isLegacyPluralFormat($value)) {
                $cldrForms = $this->convertLegacyToCldr($value, $locale);
                $converted[$key] = $cldrForms;
                $count++;

                // Collect examples
                if (count($examples) < 5) {
                    $examples[] = [
                        'key' => $key,
                        'before' => $value,
                        'after' => $cldrForms
                    ];
                }
            } else {
                // Keep non-plural translations as-is
                $converted[$key] = $value;
            }
        }

        return [
            'translations' => $converted,
            'count' => $count,
            'examples' => $examples
        ];
    }

    /**
     * Check if array is legacy plural format (numeric keys)
     */
    private function isLegacyPluralFormat(array $value): bool
    {
        $keys = array_keys($value);
        foreach ($keys as $key) {
            if (!is_int($key) && !preg_match('/^\d+(-\d+)?$/', (string)$key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Convert legacy plural format to CLDR format
     */
    private function convertLegacyToCldr(array $legacyPlurals, string $locale): array
    {
        $cldrForms = [];
        $categories = CldrPluralRules::getAvailableCategories($locale);

        // Expand ranges
        $expanded = [];
        foreach ($legacyPlurals as $key => $value) {
            if (is_string($key) && preg_match('/^(\d+)-(\d+)$/', $key, $matches)) {
                for ($i = (int)$matches[1]; $i <= (int)$matches[2]; $i++) {
                    $expanded[$i] = $value;
                }
            } else {
                $expanded[(int)$key] = $value;
            }
        }

        // Map to CLDR categories
        foreach ($expanded as $number => $text) {
            $category = CldrPluralRules::getPluralCategory($locale, (float)$number);

            // Only set if category is available for this locale
            if (in_array($category, $categories)) {
                // Prefer the lower number form for each category
                if (!isset($cldrForms[$category])) {
                    $cldrForms[$category] = $text;
                }
            }
        }

        // Ensure 'other' category exists
        if (!isset($cldrForms['other'])) {
            // Use the highest number form as 'other'
            $maxKey = max(array_keys($expanded));
            $cldrForms['other'] = $expanded[$maxKey];
        }

        // Order categories properly
        $ordered = [];
        $orderList = ['zero', 'one', 'two', 'few', 'many', 'other'];
        foreach ($orderList as $cat) {
            if (isset($cldrForms[$cat])) {
                $ordered[$cat] = $cldrForms[$cat];
            }
        }

        return $ordered;
    }

    /**
     * Show conversion examples
     */
    private function showConversionExamples(SymfonyStyle $io, array $examples, int $limit): void
    {
        if (empty($examples)) {
            return;
        }

        $io->section('Conversion Examples');

        foreach (array_slice($examples, 0, $limit) as $example) {
            $io->writeln("<comment>{$example['key']}</comment>");

            if (isset($example['locale'])) {
                $io->writeln("Locale: {$example['locale']}");
            }

            $io->writeln("Before:");
            foreach ($example['before'] as $k => $v) {
                $io->writeln("  $k: $v");
            }

            $io->writeln("After:");
            foreach ($example['after'] as $k => $v) {
                $io->writeln("  $k: $v");
            }

            $io->newLine();
        }
    }

    /**
     * Get module ID by name
     */
    private function getModuleId(string $moduleName): ?int
    {
        $result = $this->connection->query('
            SELECT [id]
            FROM [translation_modules]
            WHERE [name] = %s
            LIMIT 1
        ', $moduleName)->fetch();

        return $result ? (int)$result['id'] : null;
    }
}
