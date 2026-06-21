<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\MigrateCommand;
use Hibla\SchemaManager\Console\SchemaDumpCommand;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Hibla\await;

/**
 * Check if a CLI command exists on the host machine.
 */
function executableExists(string $command): bool
{
    $where = DIRECTORY_SEPARATOR === '\\' ? 'where' : 'which';
    $output = shell_exec(escapeshellcmd("$where $command") . ' 2>&1');

    return $output !== null && trim($output) !== '';
}

describe('SchemaDumpCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_dump_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        $requiredExecutable = match (driver()) {
            'pgsql' => 'pg_dump',
            'sqlite' => 'sqlite3',
            default => 'mysqldump',
        };

        if (! executableExists($requiredExecutable)) {
            $this->markTestSkipped("The executable '{$requiredExecutable}' is not available on this host machine. Skipping dump test.");
        }

        initializeSchema();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        Config::reset();

        // Standardize sandbox config globally for all schema dump tests
        Config::setFromRoot('hibla-migrations', 'schema_path', $tempDir . '/database/schema');
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'migrations_table', 'migrations');
        Config::setFromRoot('hibla-migrations', 'naming_convention', 'timestamp');
        Config::setFromRoot('hibla-migrations', 'timezone', 'UTC');
        Config::setFromRoot('hibla-migrations', 'recursive', true);
        Config::setFromRoot('hibla-migrations', 'safe_mode', false);
        Config::setFromRoot('hibla-migrations', 'connection_paths', []);
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
        cleanupSchema();
        Config::reset();
    });

    it('dumps the database schema successfully to a file', function () use ($tempDir) {
        $application = new Application();
        $dumpCommand = new SchemaDumpCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($dumpCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($dumpCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'create_users_table', 'users');

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        expect(await(schema()->hasTable('users')))->toBeTruthy();

        $tester = new CommandTester($dumpCommand);
        $exitCode = $tester->execute([]);

        if ($exitCode !== 0) {
            echo "\nCommand Failed with Output:\n" . $tester->getDisplay() . "\n";
        }

        expect($exitCode)->toBe(0);

        $connectionName = driver();
        // Fallback: If DB_CONNECTION is omitted, it defaults to mysql
        if ($connectionName === 'mysql' && ! isset($_SERVER['DB_CONNECTION'])) {
            $schemaFile = $tempDir . '/database/schema/mysql-schema.sql';
        } else {
            $schemaFile = $tempDir . "/database/schema/{$connectionName}-schema.sql";
        }

        expect(file_exists($schemaFile))->toBeTrue();

        $sqlContent = file_get_contents($schemaFile);
        expect(strtolower($sqlContent))->toContain('create table')
            ->and(strtolower($sqlContent))->toContain('users')
        ;
    });

    it('dumps the schema and prunes migration files when the --prune option is specified', function () use ($tempDir) {
        $application = new Application();
        $dumpCommand = new SchemaDumpCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($dumpCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($dumpCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        $file = createTestMigration($tempDir, 'create_users_table', 'users');
        $fullPath = $tempDir . '/database/migrations/' . $file;
        expect(file_exists($fullPath))->toBeTrue();

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        $tester = new CommandTester($dumpCommand);
        $exitCode = $tester->execute(['--prune' => true]);

        expect($exitCode)->toBe(0);

        $connectionName = driver();
        if ($connectionName === 'mysql' && ! isset($_SERVER['DB_CONNECTION'])) {
            $schemaFile = $tempDir . '/database/schema/mysql-schema.sql';
        } else {
            $schemaFile = $tempDir . "/database/schema/{$connectionName}-schema.sql";
        }

        expect(file_exists($schemaFile))->toBeTrue();
        expect(file_exists($fullPath))->toBeFalsy();

        $records = await(\Hibla\QueryBuilder\DB::table('migrations')->get());
        expect($records)->toHaveCount(1)
            ->and($records[0]->migration)->toContain('create_users_table')
        ;
    });
});
