<?php

declare(strict_types=1);

namespace Hibla\SchemaManager\Console;

use Hibla\SchemaManager\Console\Traits\LoadsSeederConfiguration;
use Hibla\SchemaManager\Schema\Seeder;
use Hibla\QueryBuilder\Utilities\ConfigResolver;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbSeedCommand extends Command
{
    use LoadsSeederConfiguration;

    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    protected function configure(): void
    {
        $this
            ->setName('db:seed')
            ->setDescription('Seed the database with records')
            ->addOption('class', null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder', 'DatabaseSeeder')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run without prompts')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->projectRoot ??= Config::getRootPath();

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Database Seeding');

        $force = (bool) $input->getOption('force');
        $connectionOption = $input->getOption('connection');
        $connection = (\is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connectionProtectedBySafeMode() && ! $force) {
            $this->io->error([
                'COMMAND ABORTED: SAFE MODE IS ENABLED!',
                'Seeding is restricted in this environment.',
                'To bypass this safety check, run with the --force (-f) flag.',
            ]);

            return Command::FAILURE;
        }

        $seedsPath = $this->getSeedsPath($connection);

        if (! is_dir($seedsPath)) {
            $this->io->error("Seeders directory not found: {$seedsPath}");
            $this->io->note('Ensure you have initialized your directories with `hibla-db init` or run `make:seeder` first.');

            return Command::FAILURE;
        }

        $classOption = $input->getOption('class');
        $seederClass = \is_string($classOption) && $classOption !== '' ? $classOption : null;

        if ($seederClass !== null && $seederClass !== 'DatabaseSeeder') {
            return $this->runSpecificSeeder($seedsPath, $seederClass, $connection);
        }

        $defaultPath = $seedsPath . DIRECTORY_SEPARATOR . 'DatabaseSeeder.php';
        if (file_exists($defaultPath)) {
            return $this->runSeederFile($defaultPath, 'DatabaseSeeder', $connection);
        }

        return $this->runAllSeeders($connection);
    }

    private function connectionProtectedBySafeMode(): bool
    {
        $config = ConfigResolver::getMigrationsConfig();

        return (bool) ($config['safe_mode'] ?? false);
    }

    private function runSpecificSeeder(string $seedsPath, string $seederClass, ?string $connection): int
    {
        if (class_exists($seederClass) && is_subclass_of($seederClass, Seeder::class)) {
            $this->io->writeln("Running seeder class: <comment>{$seederClass}</comment>...");

            try {
                /** @var Seeder $instance */
                $instance = new $seederClass();
                $instance->setConnection($connection);
                $instance->run();

                $this->io->success('Seeding completed successfully!');

                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $this->io->error('Seeding failed: ' . $e->getMessage());

                return Command::FAILURE;
            }
        }

        $filePath = rtrim($seedsPath, '/\\') . DIRECTORY_SEPARATOR . ltrim($seederClass, '/\\');
        if (! str_ends_with($filePath, '.php')) {
            $filePath .= '.php';
        }

        if (! file_exists($filePath)) {
            $this->io->error("Seeder file or class-string not found: {$seederClass}");

            return Command::FAILURE;
        }

        return $this->runSeederFile($filePath, $seederClass, $connection);
    }

    private function runSeederFile(string $filePath, string $name, ?string $connection): int
    {
        $this->io->writeln("Running seeder: <comment>{$name}</comment>...");

        try {
            $seeder = require $filePath;

            if (! $seeder instanceof Seeder) {
                $this->io->error("Seeder file '{$name}' must return an instance of Hibla\\Migrations\\Schema\\Seeder.");

                return Command::FAILURE;
            }

            $seeder->setConnection($connection);
            $seeder->run();

            $this->io->success('Seeding completed successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->io->error('Seeding failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function runAllSeeders(?string $connection): int
    {
        $files = $this->getAllSeederFiles($connection);

        if (\count($files) === 0) {
            $this->io->warning('No seeder files found to execute.');

            return Command::SUCCESS;
        }

        $this->io->writeln('No DatabaseSeeder found. Fallback: Executing all seeders...');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->io->write("  Seeding: <comment>{$name}</comment>...");

            try {
                $seeder = require $file;
                if ($seeder instanceof Seeder) {
                    $seeder->setConnection($connection);
                    $seeder->run();
                    $this->io->writeln(' <info>✓</info>');
                }
            } catch (\Throwable $e) {
                $this->io->writeln(' <error>✗</error>');
                $this->io->error("Seeding halted. Error in seeder '{$name}': " . $e->getMessage());

                return Command::FAILURE;
            }
        }

        $this->io->success('All auto-discovered seeders executed successfully!');

        return Command::SUCCESS;
    }
}
