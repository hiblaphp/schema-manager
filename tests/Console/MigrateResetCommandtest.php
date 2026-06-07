<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\MigrateCommand;
use Hibla\SchemaManager\Console\MigrateResetCommand;
use Hibla\QueryBuilder\DB;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Hibla\await;
use function Hibla\sleep;

describe('MigrateResetCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_reset_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        initializeSchema();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        Config::reset();
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
        cleanupSchema();
        Config::reset();
    });

    it('rolls back all migrations successfully', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'migrations_table', 'migrations');
        Config::setFromRoot('hibla-migrations', 'naming_convention', 'timestamp');
        Config::setFromRoot('hibla-migrations', 'timezone', 'UTC');
        Config::setFromRoot('hibla-migrations', 'recursive', true);
        Config::setFromRoot('hibla-migrations', 'safe_mode', false);
        Config::setFromRoot('hibla-migrations', 'connection_paths', []);

        $application = new Application();
        $resetCommand = new MigrateResetCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($resetCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($resetCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'create_users_table', 'users');
        sleep(0.1);
        createTestMigration($tempDir, 'create_posts_table', 'posts');

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        expect(await(schema()->hasTable('users')))->toBeTruthy();
        expect(await(schema()->hasTable('posts')))->toBeTruthy();

        $tester = new CommandTester($resetCommand);
        $exitCode = $tester->execute(['--force' => true]);

        expect($exitCode)->toBe(0);

        expect(await(schema()->hasTable('users')))->toBeFalsy();
        expect(await(schema()->hasTable('posts')))->toBeFalsy();

        $count = await(DB::table('migrations')->count());
        expect($count)->toBe(0);
    });

    it('only resets migrations matching the specified path option', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'migrations_table', 'migrations');
        Config::setFromRoot('hibla-migrations', 'naming_convention', 'timestamp');
        Config::setFromRoot('hibla-migrations', 'timezone', 'UTC');
        Config::setFromRoot('hibla-migrations', 'recursive', true);
        Config::setFromRoot('hibla-migrations', 'safe_mode', false);
        Config::setFromRoot('hibla-migrations', 'connection_paths', []);

        $application = new Application();
        $resetCommand = new MigrateResetCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($resetCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($resetCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'create_temp_table', 'temp_table');

        $timestamp = date('Y_m_d_His', time() + 5);
        $fileName = "{$timestamp}_create_new_name_table.php";
        $content = "<?php
use Hibla\SchemaManager\Schema\Blueprint;
use Hibla\SchemaManager\Schema\Migration;
use function Hibla\await;

return new class extends Migration {
    public function up(): void {
        await(\$this->create('new_name', function (Blueprint \$table) {
            \$table->id();
        }));
    }
    public function down(): void {
        await(\$this->dropIfExists('new_name'));
    }
};";
        $customDir = $tempDir . '/database/migrations/custom';
        mkdir($customDir, 0755, true);
        file_put_contents($customDir . '/' . $fileName, $content);

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        expect(await(schema()->hasTable('temp_table')))->toBeTruthy();
        expect(await(schema()->hasTable('new_name')))->toBeTruthy();

        $tester = new CommandTester($resetCommand);
        $exitCode = $tester->execute([
            '--force' => true,
            '--path' => 'custom',
        ]);

        expect($exitCode)->toBe(0);

        expect(await(schema()->hasTable('new_name')))->toBeFalsy();
        expect(await(schema()->hasTable('temp_table')))->toBeTruthy();
    });

    it('safely handles missing migration files by removing them from repository', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'migrations_table', 'migrations');
        Config::setFromRoot('hibla-migrations', 'naming_convention', 'timestamp');
        Config::setFromRoot('hibla-migrations', 'timezone', 'UTC');
        Config::setFromRoot('hibla-migrations', 'recursive', true);
        Config::setFromRoot('hibla-migrations', 'safe_mode', false);
        Config::setFromRoot('hibla-migrations', 'connection_paths', []);

        $application = new Application();
        $resetCommand = new MigrateResetCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($resetCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($resetCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        // 1. Create and execute a migration
        $file = createTestMigration($tempDir, 'create_profiles_table', 'profiles');
        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        expect(await(schema()->hasTable('profiles')))->toBeTruthy();

        // 2. Delete the physical migration file from disk
        $fullPath = $tempDir . '/database/migrations/' . $file;
        expect(file_exists($fullPath))->toBeTrue();
        unlink($fullPath);

        // 3. Reset
        $tester = new CommandTester($resetCommand);
        $exitCode = $tester->execute(['--force' => true]);

        expect($exitCode)->toBe(0);

        // 4. Verify warning output is printed, and the missing log is safely pruned from DB
        $output = $tester->getDisplay();
        expect($output)->toContain('Migration file not found, removed from repository');

        $count = await(DB::table('migrations')->count());
        expect($count)->toBe(0);
    });

    it('blocks execution when safe_mode is enabled', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'safe_mode', true);

        $application = new Application();
        $command = new MigrateResetCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--force' => true]);

        expect($exitCode)->toBe(1);

        $output = $tester->getDisplay();
        expect($output)->toContain('COMMAND ABORTED: SAFE MODE IS ENABLED');
    });

    it('aborts safely if confirmation is denied', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'safe_mode', false);

        $application = new Application();
        $command = new MigrateResetCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);

        $tester->setInputs(['no']);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);
        expect($tester->getDisplay())->toContain('cancelled');
    });
});
