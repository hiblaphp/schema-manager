<?php

declare(strict_types=1);

use Hibla\SchemaManager\Console\InitCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

describe('InitCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_init_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
    });

    it('initializes configuration files successfully in target directory', function () use ($tempDir) {
        $application = new Application();
        $command = new InitCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'targetRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();

        expect($output)->toContain("Configuration created: {$tempDir}/hibla-database.php")
            ->and($output)->toContain("Configuration created: {$tempDir}/hibla-migrations.php")
            ->and($output)->toContain("Configuration created: {$tempDir}/hibla-seeders.php")
        ;

        expect(file_exists($tempDir . '/hibla-database.php'))->toBeTrue()
            ->and(file_exists($tempDir . '/hibla-migrations.php'))->toBeTrue()
            ->and(file_exists($tempDir . '/hibla-seeders.php'))->toBeTrue()
        ;
    });

    it('respects force option and overwrites files without confirmation prompt', function () use ($tempDir) {
        $application = new Application();
        $command = new InitCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'targetRoot', $tempDir);

        file_put_contents($tempDir . '/hibla-database.php', 'original content');
        file_put_contents($tempDir . '/hibla-migrations.php', 'original content');
        file_put_contents($tempDir . '/hibla-seeders.php', 'original content');

        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--force' => true]);

        expect($exitCode)->toBe(0);

        $dbContent = file_get_contents($tempDir . '/hibla-database.php');
        $seedersContent = file_get_contents($tempDir . '/hibla-seeders.php');

        expect($dbContent)->toContain('<?php')
            ->and($dbContent)->not->toContain('original content')
            ->and($seedersContent)->toContain('<?php')
            ->and($seedersContent)->not->toContain('original content')
        ;
    });

    it('prompts before overwriting existing files when force is omitted and respects skip', function () use ($tempDir) {
        $application = new Application();
        $command = new InitCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'targetRoot', $tempDir);

        file_put_contents($tempDir . '/hibla-database.php', 'original content');
        file_put_contents($tempDir . '/hibla-migrations.php', 'original content');
        file_put_contents($tempDir . '/hibla-seeders.php', 'original content');

        $tester = new CommandTester($command);

        $tester->setInputs(['no', 'no', 'no']);

        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);
        $output = $tester->getDisplay();
        expect($output)->toContain('Skipped: hibla-database.php')
            ->and($output)->toContain('Skipped: hibla-migrations.php')
            ->and($output)->toContain('Skipped: hibla-seeders.php')
        ;

        expect(file_get_contents($tempDir . '/hibla-database.php'))->toBe('original content')
            ->and(file_get_contents($tempDir . '/hibla-migrations.php'))->toBe('original content')
            ->and(file_get_contents($tempDir . '/hibla-seeders.php'))->toBe('original content')
        ;
    });

    it('supports initializing configurations into custom directories with custom names', function () use ($tempDir) {
        $application = new Application();
        $command = new InitCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'targetRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--dir' => 'custom_config_dir',
            '--db-config' => 'custom-db',
            '--migrations-config' => 'custom-mig',
            '--seeders-config' => 'custom-seed',
        ]);

        expect($exitCode)->toBe(0);

        $targetDir = $tempDir . '/custom_config_dir';

        expect(file_exists($targetDir . '/custom-db.php'))->toBeTrue()
            ->and(file_exists($targetDir . '/custom-mig.php'))->toBeTrue()
            ->and(file_exists($targetDir . '/custom-seed.php'))->toBeTrue()
        ;

        $output = $tester->getDisplay();

        expect($output)->toContain('HIBLA_DB_CONFIG=custom_config_dir/custom-db')
            ->and($output)->toContain('HIBLA_MIGRATIONS_CONFIG=custom_config_dir/custom-mig')
            ->and($output)->toContain('HIBLA_SEEDERS_CONFIG=custom_config_dir/custom-seed')
        ;
    });
});
