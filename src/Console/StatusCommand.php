<?php

declare(strict_types=1);

namespace Hibla\SchemaManager\Console;

use Hibla\QueryBuilder\Utilities\ConfigResolver;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatusCommand extends Command
{
    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Check Hibla Database configuration status')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Hibla Database - Status');

        $this->projectRoot ??= Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return Command::FAILURE;
        }

        $this->displayStatusTable();

        if (! $this->allConfigurationsResolved()) {
            $this->io->note('Run: ./vendor/bin/hibla-db init');

            return Command::FAILURE;
        }

        $this->io->success('All configured!');

        return Command::SUCCESS;
    }

    private function displayStatusTable(): void
    {
        $rows = $this->buildStatusRows();
        $this->io->table(['Item', 'Status'], $rows);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function buildStatusRows(): array
    {
        if ($this->projectRoot === null) {
            throw new \LogicException('Project root must be initialized before building status rows.');
        }

        $dbConfig = ConfigResolver::getDatabaseConfig();
        $migrationsConfig = ConfigResolver::getMigrationsConfig();
        $seedersConfig = ConfigResolver::getSeedersConfig();

        $rows = [
            ['Project Root', $this->projectRoot],
            ['Database Config', $dbConfig !== null ? '✓ Resolved' : '✗ Missing'],
            ['Migrations Config', $migrationsConfig !== null ? '✓ Resolved' : '✗ Missing'],
            ['Seeders Config', $seedersConfig !== null ? '✓ Resolved' : '✗ Missing'],
        ];

        $envFile = $this->projectRoot . DIRECTORY_SEPARATOR . '.env';
        $envStatus = file_exists($envFile) ? '✓ Found' : '✗ Missing';
        $rows[] = ['.env File', $envStatus];

        return $rows;
    }

    private function allConfigurationsResolved(): bool
    {
        return ConfigResolver::getDatabaseConfig() !== null &&
               ConfigResolver::getMigrationsConfig() !== null &&
               ConfigResolver::getSeedersConfig() !== null;
    }
}
