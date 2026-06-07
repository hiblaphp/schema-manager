<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\PublishTemplatesCommand;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

describe('PublishTemplatesCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_publish_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        Config::reset();
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
        Config::reset();
    });

    it('publishes templates to the path configured in database configuration', function () use ($tempDir) {
        $targetPath = $tempDir . '/resources/views/pagination';

        Config::setFromRoot('hibla-database', 'pagination.templates_path', $targetPath);

        $application = new Application();
        $command = new PublishTemplatesCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();
        expect($output)->toContain('Published 6 template(s) successfully!');

        $expectedTemplates = [
            'bootstrap.php',
            'tailwind.php',
            'simple.php',
            'cursor-simple.php',
            'cursor-bootstrap.php',
            'cursor-tailwind.php',
        ];

        foreach ($expectedTemplates as $template) {
            expect(file_exists($targetPath . '/' . $template))->toBeTrue();
        }
    });

    it('publishes to a custom path when specified via the --path option', function () use ($tempDir) {
        $customPath = 'custom_pagination';

        $application = new Application();
        $command = new PublishTemplatesCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--path' => $customPath]);

        expect($exitCode)->toBe(0);

        $resolvedPath = $tempDir . DIRECTORY_SEPARATOR . $customPath;
        expect(file_exists($resolvedPath . '/tailwind.php'))->toBeTrue()
            ->and(file_exists($resolvedPath . '/bootstrap.php'))->toBeTrue()
        ;
    });

    it('prompts before overwriting existing templates and respects skip', function () use ($tempDir) {
        $customPath = 'custom_pagination';
        $resolvedPath = $tempDir . DIRECTORY_SEPARATOR . $customPath;

        mkdir($resolvedPath, 0755, true);

        file_put_contents($resolvedPath . '/tailwind.php', 'original tailwind');

        $application = new Application();
        $command = new PublishTemplatesCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);

        $tester->setInputs(['no']);

        $exitCode = $tester->execute(['--path' => $customPath]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();
        expect($output)->toContain('Skipped: tailwind.php');

        expect(file_get_contents($resolvedPath . '/tailwind.php'))->toBe('original tailwind');
    });

    it('respects force option and overwrites templates without prompt', function () use ($tempDir) {
        $customPath = 'custom_pagination';
        $resolvedPath = $tempDir . DIRECTORY_SEPARATOR . $customPath;

        mkdir($resolvedPath, 0755, true);
        file_put_contents($resolvedPath . '/tailwind.php', 'original tailwind');

        $application = new Application();
        $command = new PublishTemplatesCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--path' => $customPath,
            '--force' => true,
        ]);

        expect($exitCode)->toBe(0);

        $dbContent = file_get_contents($resolvedPath . '/tailwind.php');
        expect($dbContent)->toContain('<?php')
            ->and($dbContent)->not->toContain('original tailwind')
        ;
    });
});
