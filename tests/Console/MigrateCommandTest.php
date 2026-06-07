<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\MigrateCommand;
use Hibla\SchemaManager\Schema\Blueprint;
use Hibla\QueryBuilder\DB;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Hibla\await;
use function Hibla\sleep;

describe('MigrateCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_migrate_test_' . uniqid();

    beforeEach(function () use ($tempDir) {
        initializeSchema();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $configContent = "<?php
return [
    'migrations_path' => '{$tempDir}/database/migrations',
    'migrations_table' => 'migrations',
    'naming_convention' => 'timestamp',
    'timezone' => 'UTC',
    'recursive' => true,
    'safe_mode' => false,
    'connection_paths' => [],
];";
        file_put_contents($tempDir . '/hibla-migrations.php', $configContent);
    });

    afterEach(function () use ($tempDir) {
        deleteDirectory($tempDir);
        cleanupSchema();
    });

    it('runs pending migrations and logs them in the repository', function () use ($tempDir) {
        $application = new Application();
        $command = new MigrateCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'create_comments_table', 'comments');
        sleep(0.1);
        createTestMigration($tempDir, 'create_posts_table', 'posts');

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        expect(await(schema()->hasTable('posts')))->toBeTruthy();
        expect(await(schema()->hasTable('comments')))->toBeTruthy();

        $records = await(DB::table('migrations')->get());

        usort($records, fn ($a, $b) => strcmp($a->migration, $b->migration));

        expect($records)->toHaveCount(2)
            ->and($records[0]->migration)->toContain('create_comments_table')
            ->and((int) $records[0]->batch)->toBe(1)
            ->and($records[1]->migration)->toContain('create_posts_table')
            ->and((int) $records[1]->batch)->toBe(1)
        ;
    });

    it('limits migrations when step option is specified', function () use ($tempDir) {
        $application = new Application();
        $command = new MigrateCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'create_comments_table', 'comments');
        sleep(0.1);
        createTestMigration($tempDir, 'create_posts_table', 'posts');

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--step' => 1]);

        expect($exitCode)->toBe(0);

        expect(await(schema()->hasTable('comments')))->toBeTruthy();
        expect(await(schema()->hasTable('posts')))->toBeFalsy();

        $records = await(DB::table('migrations')->get());
        expect($records)->toHaveCount(1)
            ->and($records[0]->migration)->toContain('create_comments_table')
        ;
    });

    it('reports safely when there are no pending migrations to run', function () use ($tempDir) {
        $application = new Application();
        $command = new MigrateCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);
        expect($tester->getDisplay())->toContain('Nothing to migrate');
    });

    it('rolls back the batch and inserts no data on migration failure', function () use ($tempDir) {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        $application = new Application();
        $command = new MigrateCommand();
        $application->addCommand($command);

        setPrivateProperty($command, 'projectRoot', $tempDir);

        $timestamp1 = date('Y_m_d_His');
        $insertContent = "<?php
use Hibla\SchemaManager\Schema\Migration;
return new class extends Migration {
    public function up(): void {
        await(\$this->db('users')->insert(['name' => 'ShouldRollback']));
    }
    public function down(): void {}
};";
        mkdir($tempDir . '/database/migrations', 0755, true);
        file_put_contents($tempDir . "/database/migrations/{$timestamp1}_insert_user.php", $insertContent);

        sleep(0.1);
        $timestamp2 = date('Y_m_d_His');
        $failingContent = "<?php
use Hibla\SchemaManager\Schema\Migration;
return new class extends Migration {
    public function up(): void {
        throw new \RuntimeException('Simulation of failure');
    }
    public function down(): void {}
};";
        file_put_contents($tempDir . "/database/migrations/{$timestamp2}_failing_migration.php", $failingContent);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(1);

        $count = await(DB::table('users')->count());
        expect($count)->toBe(0);

        $migrationCount = await(DB::table('migrations')->count());
        expect($migrationCount)->toBe(0);
    });
});
