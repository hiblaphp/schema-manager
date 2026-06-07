<?php

declare(strict_types=1);

namespace Hibla\SchemaManager\Console;

use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    /**
     * The directory where configuration files will be copied to.
     * Decoupled from projectRoot to allow testing in isolation.
     */
    private ?string $targetRoot = null;

    private bool $force;

    private bool $isCustomConfig = false;

    private string $customDbPath = '';

    private string $customMigrationsPath = '';

    private string $customSeedersPath = '';

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize Hibla Database configuration')
            ->setHelp('Copies the default configuration files (Database, Migrations, Seeders) to your project.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration')
            ->addOption('dir', 'd', InputOption::VALUE_OPTIONAL, 'Target directory relative to project root (e.g., "config")', '')
            ->addOption('db-config', null, InputOption::VALUE_OPTIONAL, 'Name for the database config file (without .php)', 'hibla-database')
            ->addOption('migrations-config', null, InputOption::VALUE_OPTIONAL, 'Name for the migrations config file (without .php)', 'hibla-migrations')
            ->addOption('seeders-config', null, InputOption::VALUE_OPTIONAL, 'Name for the seeders config file (without .php)', 'hibla-seeders')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->force = (bool) $input->getOption('force');

        $this->io->title('Hibla Database - Initialize');

        $this->projectRoot = Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return Command::FAILURE;
        }

        // Default the destination target to the project root unless overridden in tests
        $this->targetRoot ??= $this->projectRoot;

        // Parse custom directory option
        $dirOption = $input->getOption('dir');
        $dirOption = \is_string($dirOption) ? trim($dirOption, '/\\') : '';

        $targetDir = $dirOption === ''
            ? $this->targetRoot
            : $this->targetRoot . DIRECTORY_SEPARATOR . $dirOption;

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $dbConfigOption = $input->getOption('db-config');
        $migrationsConfigOption = $input->getOption('migrations-config');
        $seedersConfigOption = $input->getOption('seeders-config');

        $dbFileName = (\is_string($dbConfigOption) ? $dbConfigOption : 'hibla-database') . '.php';
        $migrationsFileName = (\is_string($migrationsConfigOption) ? $migrationsConfigOption : 'hibla-migrations') . '.php';
        $seedersFileName = (\is_string($seedersConfigOption) ? $seedersConfigOption : 'hibla-seeders') . '.php';

        $this->isCustomConfig = $dirOption !== ''
            || $dbFileName !== 'hibla-database.php'
            || $migrationsFileName !== 'hibla-migrations.php'
            || $seedersFileName !== 'hibla-seeders.php';

        $prefix = $dirOption !== '' ? $dirOption . '/' : '';
        $this->customDbPath = $prefix . str_replace('.php', '', $dbFileName);
        $this->customMigrationsPath = $prefix . str_replace('.php', '', $migrationsFileName);
        $this->customSeedersPath = $prefix . str_replace('.php', '', $seedersFileName);

        if ($this->copyConfigFiles($targetDir, $dbFileName, $migrationsFileName, $seedersFileName) === Command::FAILURE) {
            return Command::FAILURE;
        }

        $this->promptEnvFileCreation($dirOption, $dbFileName, $migrationsFileName, $seedersFileName);

        return Command::SUCCESS;
    }

    private function copyConfigFiles(
        string $targetDir,
        string $dbFileName,
        string $migrationsFileName,
        string $seedersFileName
    ): int {
        $files = [
            $dbFileName => $this->getSourceConfigPath('hibla-database.php'),
            $migrationsFileName => $this->getSourceConfigPath('hibla-migrations.php'),
            $seedersFileName => $this->getSourceConfigPath('hibla-seeders.php'),
        ];

        $copiedFiles = [];
        $skippedFiles = [];
        $failedFiles = [];

        foreach ($files as $filename => $sourceConfig) {
            $result = $this->copyFile($filename, $sourceConfig, $targetDir);

            if ($result === 'copied') {
                $copiedFiles[] = $filename;
            } elseif ($result === 'skipped') {
                $skippedFiles[] = $filename;
            } else {
                $failedFiles[] = $filename;
            }
        }

        foreach ($copiedFiles as $filename) {
            $this->io->success("✓ Configuration created: {$targetDir}/{$filename}");
        }

        return \count($failedFiles) === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function copyFile(string $filename, string $sourceConfig, string $targetDir): string
    {
        if (! file_exists($sourceConfig)) {
            $this->io->error("Source config template not found: {$sourceConfig}");

            return 'failed';
        }

        $destConfig = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($destConfig) && ! $this->force) {
            if (! $this->io->confirm("File '{$filename}' already exists in the target folder. Overwrite?", false)) {
                $this->io->warning("Skipped: {$filename}");

                return 'skipped';
            }
        }

        if (! copy($sourceConfig, $destConfig)) {
            $this->io->error("Failed to copy {$filename} to target directory");

            return 'failed';
        }

        return 'copied';
    }

    private function promptEnvFileCreation(
        string $dirOption,
        string $dbFileName,
        string $migrationsFileName,
        string $seedersFileName
    ): void {
        $envLines = [
            'DB_CONNECTION=mysql',
            'DB_HOST=127.0.0.1',
            'DB_PORT=3306',
            'DB_DATABASE=test',
            'DB_USERNAME=root',
            'DB_PASSWORD=',
        ];

        $isCustomDir = $dirOption !== '' && $dirOption !== 'config';
        $isCustomDbName = $dbFileName !== 'hibla-database.php';
        $isCustomMigrationsName = $migrationsFileName !== 'hibla-migrations.php';
        $isCustomSeedersName = $seedersFileName !== 'hibla-seeders.php';

        $requiresEnvMapping = $isCustomDir || $isCustomDbName || $isCustomMigrationsName || $isCustomSeedersName;

        if ($requiresEnvMapping) {
            $envLines[] = '';
            $envLines[] = '# Hibla Custom Configuration Paths';
            $envLines[] = "HIBLA_DB_CONFIG={$this->customDbPath}";
            $envLines[] = "HIBLA_MIGRATIONS_CONFIG={$this->customMigrationsPath}";
            $envLines[] = "HIBLA_SEEDERS_CONFIG={$this->customSeedersPath}";
        }

        if ($this->projectRoot !== null && ! file_exists($this->projectRoot . '/.env')) {
            $this->io->section('Create .env file in project root with:');
            $this->io->listing($envLines);
        } elseif ($requiresEnvMapping) {
            $this->io->section('Important! Add these to your existing .env file:');
            $this->io->listing([
                "HIBLA_DB_CONFIG={$this->customDbPath}",
                "HIBLA_MIGRATIONS_CONFIG={$this->customMigrationsPath}",
                "HIBLA_SEEDERS_CONFIG={$this->customSeedersPath}",
            ]);
        }

        if ($this->isCustomConfig && ! $requiresEnvMapping) {
            $this->io->note("Since you placed the default files in the 'config' directory, they will be auto-discovered. No .env variables required!");
        }
    }

    /**
     * Get the absolute path to the configuration templates inside this package.
     */
    private function getSourceConfigPath(string $filename): string
    {
        if ($filename === 'hibla-database.php') {
            return $this->projectRoot . '/vendor/hiblaphp/query-builder/' . $filename;
        }

        return \dirname(__DIR__, 2) . '/' . $filename;
    }
}
