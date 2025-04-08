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

#[Console\Attribute\AsCommand(
    name: 'translations:export-neon',
    description: 'Export translations from the database to NEON files',
    hidden: false
)]
class ExportNeonTranslationsCommand extends Console\Command\Command
{
    private Connection $connection;

    public static function getDefaultName(): ?string
    {
        return 'translations:export-neon';
    }

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->setName('translations:export-neon')
            ->setDescription('Export translations from the database to NEON files')
            ->addArgument('module', InputArgument::REQUIRED, 'Translation module name to export')
            ->addArgument('locale', InputArgument::REQUIRED, 'Locale to export (e.g., en_US, cs_CZ)')
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Directory to save the NEON file', './translations')
            ->addOption('include-keys', 'k', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Include only specific keys (can be specified multiple times)')
            ->addOption('include-untranslated', 'u', InputOption::VALUE_NONE, 'Include keys that don\'t have translations for the specified locale');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Exporting translations from database to NEON file');

        $moduleName = $input->getArgument('module');
        $locale = $input->getArgument('locale');
        $outputDir = rtrim($input->getOption('output-dir'), '/\\');
        $includeKeys = $input->getOption('include-keys');
        $includeUntranslated = $input->getOption('include-untranslated');

        // Check if output directory exists, create it if it doesn't
        if (!is_dir($outputDir)) {
            $io->note("Creating output directory: $outputDir");
            if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                $io->error("Failed to create output directory: $outputDir");
                return Console\Command\Command::FAILURE;
            }
        }

        // Check if module exists
        $moduleId = $this->getModuleId($moduleName);
        if (!$moduleId) {
            $io->error("Module '$moduleName' does not exist or is not active.");
            return Console\Command\Command::FAILURE;
        }

        // Get translations from the database
        $translations = $this->getTranslations($moduleId, $locale, $includeKeys, $includeUntranslated);
        if (empty($translations)) {
            $io->warning("No translations found for module '$moduleName' and locale '$locale'.");
            return Console\Command\Command::FAILURE;
        }

        // Build NEON content
        $neonContent = $this->buildNeonContent($translations);

        // Save to file
        $filename = "$outputDir/$locale.neon";
        try {
            file_put_contents($filename, $neonContent);
            $io->success("Translations exported to $filename");
            $io->table(
                ['Statistics'],
                [
                    ['Total Keys', count($translations)],
                ]
            );
            return Console\Command\Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to write to file: " . $e->getMessage());
            return Console\Command\Command::FAILURE;
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
            WHERE [name] = %s AND [is_active] = 1
            LIMIT 1
        ', $moduleName)->fetch();

        return $result ? (int)$result['id'] : null;
    }

    /**
     * Get translations from database
     *
     * @param int $moduleId
     * @param string $locale
     * @param array $includeKeys
     * @param bool $includeUntranslated
     * @return array<string, mixed> Associative array of key => value translations
     */
    private function getTranslations(int $moduleId, string $locale, array $includeKeys, bool $includeUntranslated): array
    {
        $query = $this->connection->select('k.key, k.type, t.value, t.plural_values')
            ->from('[translation_keys] k')
            ->where('k.module_id = %i', $moduleId);

        // Filter by specific keys if provided
        if (!empty($includeKeys)) {
            $query->where('k.key IN %in', $includeKeys);
        }

        if ($includeUntranslated) {
            $query->leftJoin('[translations] t')->on('k.id = t.key_id AND t.locale = %s', $locale);
        } else {
            $query->join('[translations] t')->on('k.id = t.key_id AND t.locale = %s', $locale)
                ->where('t.value IS NOT NULL OR t.plural_values IS NOT NULL');
        }

        $rows = $query->fetchAll();
        $translations = [];

        foreach ($rows as $row) {
            $key = $row['key'];

            // Handle different translation types
            if ($row['type'] === 'plural' && $row['plural_values']) {
                // Plural values are stored as JSON, decode them
                $pluralValues = json_decode($row['plural_values'], true);
                if ($pluralValues) {
                    $translations[$key] = $pluralValues;
                }
            } else if ($row['value'] !== null && $row['value'] !== '') {
                // Text and HTML types
                $translations[$key] = $row['value'];
            } else if ($includeUntranslated) {
                // Include untranslated keys with an empty value
                $translations[$key] = '';
            }
        }

        return $translations;
    }

    /**
     * Build NEON content from translations array
     */
    private function buildNeonContent(array $translations): string
    {
        // Sort translations alphabetically by key for consistency
        ksort($translations);

        $neonArray = [];

        foreach ($translations as $key => $value) {
            if (is_array($value)) {
                // Format plural values according to NEON structure
                $neonArray[$key] = $value;
            } else {
                $neonArray[$key] = $value;
            }
        }

        // Encode to NEON format with block mode (true as second parameter)
        return Neon::encode($neonArray, true);
    }
}
