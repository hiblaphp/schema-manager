<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\MigrateCommand;
use Hibla\SchemaManager\Console\MigrateFreshCommand;
use Hibla\SchemaManager\Schema\Blueprint;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Hibla\await;
use function Hibla\sleep;

describe('MigrateFreshCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_fresh_test_' . uniqid();

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

    it('drops all existing tables and re-runs migrations', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'migrations_path', $tempDir . '/database/migrations');
        Config::setFromRoot('hibla-migrations', 'migrations_table', 'migrations');
        Config::setFromRoot('hibla-migrations', 'naming_convention', 'timestamp');
        Config::setFromRoot('hibla-migrations', 'timezone', 'UTC');
        Config::setFromRoot('hibla-migrations', 'recursive', true);
        Config::setFromRoot('hibla-migrations', 'safe_mode', false);
        Config::setFromRoot('hibla-migrations', 'connection_paths', []);

        $application = new Application();

        $freshCommand = new MigrateFreshCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($freshCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($freshCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        await(schema()->create('rogue_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        expect(await(schema()->hasTable('rogue_table')))->toBeTruthy();

        createTestMigration($tempDir, 'create_fresh_posts_table', 'fresh_posts');
        sleep(0.1);
        createTestMigration($tempDir, 'create_fresh_comments_table', 'fresh_comments');

        sleep(0.05);

        $tester = new CommandTester($freshCommand);

        $exitCode = $tester->execute(['--force' => true]);

        if ($exitCode !== 0) {
            echo "\nCommand Failed with Output:\n" . $tester->getDisplay() . "\n";
        }

        expect($exitCode)->toBe(0);
        expect(await(schema()->hasTable('rogue_table')))->toBeFalsy();
        expect(await(schema()->hasTable('fresh_posts')))->toBeTruthy();
        expect(await(schema()->hasTable('fresh_comments')))->toBeTruthy();
    });

    it('aborts safely if confirmation is denied', function () use ($tempDir) {
        $application = new Application();
        $command = new MigrateFreshCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        await(schema()->create('protected_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        $tester = new CommandTester($command);

        $tester->setInputs(['no']);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();
        expect($output)->toContain('Fresh migration cancelled');

        expect(await(schema()->hasTable('protected_table')))->toBeTruthy();
    });

    it('blocks execution when safe_mode is enabled', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'safe_mode', true);

        $application = new Application();
        $command = new MigrateFreshCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--force' => true]);

        expect($exitCode)->toBe(1);

        $output = $tester->getDisplay();
        expect($output)->toContain('COMMAND ABORTED: SAFE MODE IS ENABLED');
    });
});
