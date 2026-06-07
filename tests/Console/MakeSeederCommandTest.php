<?php

declare(strict_types=1);

use Hibla\SchemaManager\Console\MakeSeederCommand;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

describe('MakeSeederCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_make_seeder_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        Config::reset();
        Config::setFromRoot('hibla-seeders', 'seeds_path', $tempDir . '/database/seeders');
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
        Config::reset();
    });

    it('creates a blank seeder file successfully', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeSeederCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'user',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/seeders/*.php');
        expect($files)->toHaveCount(1);
        expect(basename($files[0]))->toBe('UserSeeder.php');

        $content = file_get_contents($files[0]);
        expect($content)->toContain('extends Seeder')
            ->and($content)->toContain('Run the database seeds.')
        ;
    });

    it('creates seeder inside nested subdirectories', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeSeederCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'testing/post',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/seeders/testing/*.php');
        expect($files)->toHaveCount(1);
        expect(basename($files[0]))->toBe('PostSeeder.php');
    });

    it('injects custom connection attributes into seeder template', function () use ($tempDir) {
        $application = new Application();
        $command = new MakeSeederCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'name' => 'LogSeeder',
            '--connection' => 'pgsql',
        ]);

        expect($exitCode)->toBe(0);

        $files = glob($tempDir . '/database/seeders/*.php');
        $content = file_get_contents($files[0]);
        expect($content)->toContain("protected ?string \$connection = 'pgsql';");
    });
});
