<?php

declare(strict_types=1);

use Hibla\SchemaManager\Console\MakeMigrationCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

describe('MakeMigrationCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_make_migration_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
    });

    it('creates a blank migration file successfully', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeMigrationCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'blank_migration',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/migrations/*.php');
        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('extends Migration')
            ->and($content)->toContain('Run the migration')
            ->and($content)->not->toContain('$this->create')
            ->and($content)->not->toContain('$this->table')
        ;
    });

    it('creates a table creation migration file', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeMigrationCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'create_users_table',
            '--table' => 'users',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/migrations/*.php');
        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("\$this->create('users'")
            ->and($content)->toContain("\$this->dropIfExists('users'")
        ;
    });

    it('auto-detects table creation from migration name', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeMigrationCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'create_posts_table',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/migrations/*.php');
        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("\$this->create('posts'")
            ->and($content)->toContain("\$this->dropIfExists('posts'")
        ;
    });

    it('creates an alter table migration file', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeMigrationCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'add_phone_to_users_table',
            '--alter' => 'users',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/migrations/*.php');
        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("\$this->table('users'")
            ->and($content)->not->toContain('$this->create')
            ->and($content)->not->toContain('$this->dropIfExists')
        ;
    });

    it('creates migration with custom connection attribute', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeMigrationCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'create_orders_table',
            '--table' => 'orders',
            '--connection' => 'pgsql',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/migrations/*.php');
        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("protected ?string \$connection = 'pgsql';");
    });

    it('creates migration in subdirectories', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeMigrationCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'backup/create_settings_table',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/migrations/backup/*.php');
        expect($files)->toHaveCount(1);
    });

    it('creates migration in custom path via option', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeMigrationCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'create_roles_table',
            '--path' => 'custom_path',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/migrations/custom_path/*.php');
        expect($files)->toHaveCount(1);
    });
});
