<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\MigrateCommand;
use Hibla\SchemaManager\Console\MigrateRefreshCommand;
use Hibla\SchemaManager\Console\MigrateResetCommand;
use Hibla\SchemaManager\Console\MigrateRollbackCommand;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Hibla\await;
use function Hibla\sleep;

describe('MigrateRefreshCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_refresh_test_' . uniqid();

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

    it('rolls back all migrations and re-runs them', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'migrations_table', 'migrations');
        Config::setFromRoot('hibla-migrations', 'naming_convention', 'timestamp');
        Config::setFromRoot('hibla-migrations', 'timezone', 'UTC');
        Config::setFromRoot('hibla-migrations', 'recursive', true);
        Config::setFromRoot('hibla-migrations', 'safe_mode', false);
        Config::setFromRoot('hibla-migrations', 'connection_paths', []);

        $application = new Application();

        $refreshCommand = new MigrateRefreshCommand();
        // Refresh depends on reset and migrate internally
        $resetCommand = new MigrateResetCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($refreshCommand);
        $application->addCommand($resetCommand);
        $application->addCommand($migrateCommand);

        // Inject sandbox root ONLY into commands that actually have the property
        setPrivateProperty($resetCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        // 1. Create and run migrations initially
        createTestMigration($tempDir, 'create_refresh_users_table', 'refresh_users');
        sleep(0.1);
        createTestMigration($tempDir, 'create_refresh_posts_table', 'refresh_posts');

        $initialMigrateTester = new CommandTester($migrateCommand);
        $initialMigrateTester->execute([]);

        expect(await(schema()->hasTable('refresh_users')))->toBeTruthy();
        expect(await(schema()->hasTable('refresh_posts')))->toBeTruthy();

        // 2. Execute Refresh Command
        $tester = new CommandTester($refreshCommand);
        $exitCode = $tester->execute(['--force' => true]);

        if ($exitCode !== 0) {
            echo "\nCommand Failed with Output:\n" . $tester->getDisplay() . "\n";
        }

        expect($exitCode)->toBe(0);

        // Verify output confirms both reset and migration occurred
        $output = $tester->getDisplay();
        expect($output)->toContain('Resetting database') // <-- FIXED: Expect 'Resetting database' on full refresh
            ->and($output)->toContain('Running migrations')
        ;

        // 3. Verify tables still exist (because they were dropped and immediately recreated)
        expect(await(schema()->hasTable('refresh_users')))->toBeTruthy();
        expect(await(schema()->hasTable('refresh_posts')))->toBeTruthy();
    });

    it('respects the step option by rolling back only a specific number of batches', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'migrations_table', 'migrations');
        Config::setFromRoot('hibla-migrations', 'naming_convention', 'timestamp');
        Config::setFromRoot('hibla-migrations', 'timezone', 'UTC');
        Config::setFromRoot('hibla-migrations', 'recursive', true);
        Config::setFromRoot('hibla-migrations', 'safe_mode', false);
        Config::setFromRoot('hibla-migrations', 'connection_paths', []);

        $application = new Application();

        $refreshCommand = new MigrateRefreshCommand();
        // When step is provided, it uses rollback instead of reset!
        $rollbackCommand = new MigrateRollbackCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($refreshCommand);
        $application->addCommand($rollbackCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($rollbackCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        // 1. Run first migration (Batch 1)
        createTestMigration($tempDir, 'create_step_users_table', 'step_users');
        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        sleep(0.1);

        // 2. Run second migration (Batch 2)
        createTestMigration($tempDir, 'create_step_posts_table', 'step_posts');
        $migrateTester->execute([]);

        // 3. Execute Refresh with step=1
        // This should ONLY rollback and remigrate Batch 2 (step_posts)
        $tester = new CommandTester($refreshCommand);
        $exitCode = $tester->execute([
            '--force' => true,
            '--step' => 1,
        ]);

        // Added debug printing to see exactly what fails if it returns 1 again!
        if ($exitCode !== 0) {
            echo "\nCommand Failed with Output:\n" . $tester->getDisplay() . "\n";
        }

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();
        expect($output)->toContain('Rolling back migrations')
            ->and($output)->toContain('create_step_posts_table')
            ->and($output)->not->toContain('create_step_users_table') // Should not have touched Batch 1
        ;

        expect(await(schema()->hasTable('step_users')))->toBeTruthy();
        expect(await(schema()->hasTable('step_posts')))->toBeTruthy();
    });

    it('blocks execution when safe_mode is enabled', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'safe_mode', true);

        $application = new Application();
        $command = new MigrateRefreshCommand();
        $application->addCommand($command);

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
        $command = new MigrateRefreshCommand();
        $application->addCommand($command);

        $tester = new CommandTester($command);

        $tester->setInputs(['no']);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);
        expect($tester->getDisplay())->toContain('cancelled');
    });
});
