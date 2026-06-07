<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\DbSeedCommand;
use Hibla\SchemaManager\Schema\Blueprint;
use Hibla\QueryBuilder\DB;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Hibla\await;

function createTestSeeder(string $projectRoot, string $name, string $table, array $data): string
{
    $fileName = "{$name}.php";
    $dataExport = var_export($data, true);

    $content = "<?php
use Hibla\SchemaManager\Schema\Seeder;
use function Hibla\await;

return new class extends Seeder {
    public function run(): void {
        await(\$this->db('{$table}')->insert({$dataExport}));
    }
};";

    $dir = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders';
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir . DIRECTORY_SEPARATOR . $fileName, $content);

    return $fileName;
}

describe('DbSeedCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_seed_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        initializeSchema();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        Config::reset();
        Config::setFromRoot('hibla-seeders', 'seeds_path', $tempDir . '/database/seeders');
        Config::setFromRoot('hibla-seeders', 'recursive', true);
        Config::setFromRoot('hibla-seeders', 'connection_paths', []);

        Config::setFromRoot('hibla-migrations', 'safe_mode', false);

        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        }));
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
        cleanupSchema();
        Config::reset();
    });

    it('runs a specific seeder class via --class option', function () use ($tempDir) {
        $application = new Application();
        $command = new DbSeedCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        createTestSeeder($tempDir, 'UserSeeder', 'users', ['name' => 'Alice']);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--class' => 'UserSeeder',
        ]);

        expect($exitCode)->toBe(0);

        $count = await(DB::table('users')->count());
        expect($count)->toBe(1);

        $user = await(DB::table('users')->first());
        expect($user->name)->toBe('Alice');
    });

    it('orchestrates seeding using the default DatabaseSeeder calling nested seeders', function () use ($tempDir) {
        $application = new Application();
        $command = new DbSeedCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        createTestSeeder($tempDir, 'UserSeeder', 'users', ['name' => 'Alice']);
        createTestSeeder($tempDir, 'PostSeeder', 'posts', ['title' => 'Hello World']);

        $dir = $tempDir . '/database/seeders';
        $masterContent = "<?php
use Hibla\SchemaManager\Schema\Seeder;
use function Hibla\await;

return new class extends Seeder {
    public function run(): void {
        await(\$this->call('UserSeeder'));
        await(\$this->call('PostSeeder'));
    }
};";
        file_put_contents($dir . '/DatabaseSeeder.php', $masterContent);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $userCount = await(DB::table('users')->count());
        $postCount = await(DB::table('posts')->count());

        expect($userCount)->toBe(1)
            ->and($postCount)->toBe(1)
        ;
    });

    it('auto-discovers and runs all seeders in alphabetical order if DatabaseSeeder is missing', function () use ($tempDir) {
        $application = new Application();
        $command = new DbSeedCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        createTestSeeder($tempDir, 'A_UserSeeder', 'users', ['name' => 'Alice']);
        createTestSeeder($tempDir, 'B_PostSeeder', 'posts', ['title' => 'Hello']);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();
        expect($output)->toContain('Executing all seeders')
            ->and($output)->toContain('A_UserSeeder')
            ->and($output)->toContain('B_PostSeeder')
        ;

        $userCount = await(DB::table('users')->count());
        $postCount = await(DB::table('posts')->count());

        expect($userCount)->toBe(1)
            ->and($postCount)->toBe(1)
        ;
    });

    it('blocks execution when safe_mode is active unless force option is passed', function () use ($tempDir) {
        Config::setFromRoot('hibla-migrations', 'safe_mode', true);

        $application = new Application();
        $command = new DbSeedCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        createTestSeeder($tempDir, 'UserSeeder', 'users', ['name' => 'Blocked']);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(Command::FAILURE);

        $output = $tester->getDisplay();
        expect($output)->toContain('COMMAND ABORTED: SAFE MODE IS ENABLED');

        $exitCodeForce = $tester->execute(['--force' => true]);
        expect($exitCodeForce)->toBe(Command::SUCCESS);

        $count = await(DB::table('users')->count());
        expect($count)->toBe(1);
    });
});
