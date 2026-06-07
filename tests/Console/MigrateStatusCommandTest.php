<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\MigrateCommand;
use Hibla\SchemaManager\Console\MigrateStatusCommand;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Hibla\sleep;

describe('MigrateStatusCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_status_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        initializeSchema();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        Config::reset();
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

    it('shows the correct status table and stats for pending and completed migrations', function () use ($tempDir) {
        $application = new Application();
        $statusCommand = new MigrateStatusCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($statusCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($statusCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);
        createTestMigration($tempDir, 'a_create_users_table', 'users');
        sleep(0.1);
        createTestMigration($tempDir, 'b_create_posts_table', 'posts');

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute(['--step' => 1]);

        $tester = new CommandTester($statusCommand);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();

        expect($output)->toContain('a_create_users_table')
            ->and($output)->toContain('Ran')
            ->and($output)->toContain('b_create_posts_table')
            ->and($output)->toContain('Pending')
            ->and($output)->toContain('Total migrations: 2')
            ->and($output)->toContain('Completed: 1')
            ->and($output)->toContain('Pending: 1')
        ;
    });

    it('can filter results to show pending migrations only', function () use ($tempDir) {
        $application = new Application();
        $statusCommand = new MigrateStatusCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($statusCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($statusCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'a_create_users_table', 'users');
        sleep(0.1);
        createTestMigration($tempDir, 'b_create_posts_table', 'posts');

        // Run step=1 (users is ran, posts is pending)
        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute(['--step' => 1]);

        // Run status with --pending
        $tester = new CommandTester($statusCommand);
        $exitCode = $tester->execute(['--pending' => true]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();

        // Verify only the pending migration is shown
        expect($output)->toContain('b_create_posts_table')
            ->and($output)->toContain('Pending')
            ->and($output)->not->toContain('a_create_users_table')
            ->and($output)->not->toContain('Ran')
        ;
    });

    it('can filter results to show completed migrations only', function () use ($tempDir) {
        $application = new Application();
        $statusCommand = new MigrateStatusCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($statusCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($statusCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'a_create_users_table', 'users');
        sleep(0.1);
        createTestMigration($tempDir, 'b_create_posts_table', 'posts');

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute(['--step' => 1]);

        $tester = new CommandTester($statusCommand);
        $exitCode = $tester->execute(['--ran' => true]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();

        expect($output)->toContain('a_create_users_table')
            ->and($output)->toContain('Ran')
            ->and($output)->not->toContain('b_create_posts_table')
        ;
    });

    it('displays pruned status for migrations executed but deleted from disk', function () use ($tempDir) {
        $application = new Application();
        $statusCommand = new MigrateStatusCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($statusCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($statusCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        $file = createTestMigration($tempDir, 'create_profiles_table', 'profiles');
        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        $fullPath = $tempDir . '/database/migrations/' . $file;
        expect(file_exists($fullPath))->toBeTrue();
        unlink($fullPath);

        $tester = new CommandTester($statusCommand);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();

        expect($output)->toContain('create_profiles_table')
            ->and($output)->toContain('Ran (Pruned)')
        ;
    });
});
