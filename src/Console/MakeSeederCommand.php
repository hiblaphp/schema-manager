<?php

declare(strict_types=1);

namespace Hibla\SchemaManager\Console;

use Hibla\SchemaManager\Console\Traits\LoadsSeederConfiguration;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeSeederCommand extends Command
{
    use LoadsSeederConfiguration;

    private SymfonyStyle $io;

    private ?string $projectRoot = null;

    private string $seedsPath;

    private string $seederName;

    private ?string $connection = null;

    private ?string $subdirectory = null;

    protected function configure(): void
    {
        $this
            ->setName('make:seeder')
            ->setDescription('Create a new database seeder file')
            ->addArgument('name', InputArgument::REQUIRED, 'Seeder name (supports subdirectories, e.g., testing/UserSeeder)')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Custom subdirectory path for the seeder')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Create Seeder');

        $connectionOption = $input->getOption('connection');
        $this->connection = (\is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }

        $seederNameValue = $input->getArgument('name');
        if (! \is_string($seederNameValue) || trim($seederNameValue) === '') {
            $this->io->error('The seeder name must be a non-empty string.');

            return Command::FAILURE;
        }

        $this->parseSeederName($seederNameValue);

        $pathOption = $input->getOption('path');
        if (\is_string($pathOption) && $pathOption !== '') {
            $this->subdirectory = trim($pathOption, '/\\');
        }

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        if (! $this->ensureSeedsDirectory()) {
            return Command::FAILURE;
        }

        if (! $this->createSeederFile()) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function initializeProjectRoot(): bool
    {
        $this->projectRoot ??= Config::getRootPath();

        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root. Ensure a vendor directory exists.');

            return false;
        }

        return true;
    }

    private function parseSeederName(string $input): void
    {
        $normalized = str_replace('\\', '/', $input);

        if (str_contains($normalized, '/')) {
            $parts = explode('/', $normalized);
            $this->seederName = array_pop($parts);

            if ($this->subdirectory === null) {
                $this->subdirectory = implode(DIRECTORY_SEPARATOR, $parts);
            }
        } else {
            $this->seederName = $input;
        }

        $this->seederName = $this->sanitizeSeederName($this->seederName);
    }

    private function sanitizeSeederName(string $name): string
    {
        $name = str_replace(['/', '\\', '.php'], '', $name);

        if (! str_ends_with(strtolower($name), 'seeder')) {
            $name .= 'Seeder';
        }

        return ucfirst($name);
    }

    private function ensureSeedsDirectory(): bool
    {
        $basePath = $this->getSeedsPath($this->connection);

        if ($this->subdirectory !== null) {
            $this->seedsPath = $basePath . DIRECTORY_SEPARATOR . $this->subdirectory;
        } else {
            $this->seedsPath = $basePath;
        }

        // Create the seeds directory recursively if it doesn't exist
        if (! is_dir($this->seedsPath) && ! @mkdir($this->seedsPath, 0755, true)) {
            $this->io->error("Failed to create seeds directory: {$this->seedsPath}");

            return false;
        }

        return true;
    }

    private function createSeederFile(): bool
    {
        $fileName = "{$this->seederName}.php";
        $filePath = $this->seedsPath . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            $this->io->error("Seeder file already exists: {$fileName}");

            return false;
        }

        $stub = $this->generateSeederStub();

        if (file_put_contents($filePath, $stub) === false) {
            $this->io->error('Failed to create seeder file');

            return false;
        }

        $displayPath = $this->subdirectory !== null
            ? $this->subdirectory . DIRECTORY_SEPARATOR . $fileName
            : $fileName;

        $displayPath = str_replace('\\', '/', $displayPath);

        $this->io->success("Seeder created: {$displayPath}");

        return true;
    }

    private function generateSeederStub(): string
    {
        $connectionLine = $this->connection !== null
            ? "    protected ?string \$connection = '{$this->connection}';\n\n"
            : '';

        return "<?php

use Hibla\SchemaManager\Schema\Seeder;

use function Hibla\await;

return new class extends Seeder
{
{$connectionLine}    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Write your database seeding operations here
        // await(\$this->db('users')->insert([
        //     'name' => 'John Doe',
        //     'email' => 'john@example.com',
        // ]));
    }
};
";
    }
}
