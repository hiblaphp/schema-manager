<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\StatusCommand;
use Hibla\QueryBuilder\Utilities\ConfigResolver;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

describe('StatusCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_status_command_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        ConfigResolver::$mocks = null;
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
        ConfigResolver::$mocks = null;
    });

    it('reports success when all configuration files and .env exist', function () use ($tempDir) {
        ConfigResolver::$mocks = [
            'database' => [],
            'migrations' => [],
            'seeders' => [],
        ];

        $application = new Application();
        $command = new StatusCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        file_put_contents($tempDir . '/.env', 'DB_CONNECTION=mysql');

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();
        expect($output)->toContain('✓ Resolved')
            ->and($output)->toContain('All configured!')
            ->and($output)->not->toContain('✗ Missing')
        ;
    });

    it('reports failure and outputs instructions when configuration files are missing', function () use ($tempDir) {
        ConfigResolver::$mocks = [
            'database' => null,
            'migrations' => null,
            'seeders' => null,
        ];

        $application = new Application();
        $command = new StatusCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(1);

        $output = $tester->getDisplay();

        expect($output)->toContain('✗ Missing')
            ->and($output)->toContain('Run: ./vendor/bin/hibla-db init')
            ->and($output)->not->toContain('All configured!')
        ;
    });
});
