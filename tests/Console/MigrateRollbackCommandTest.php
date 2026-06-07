<?php

declare(strict_types=1);

namespace Tests\Console;

use Hibla\SchemaManager\Console\MigrateCommand;
use Hibla\SchemaManager\Console\MigrateRollbackCommand;
use Hibla\QueryBuilder\DB;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Hibla\await;

describe('MigrateRollbackCommand', function () {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hibla_rollback_test_' . uniqid();

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

    it('rolls back the last migration by default (step 1)', function () use ($tempDir) {
        $application = new Application();
        $rollbackCommand = new MigrateRollbackCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($rollbackCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($rollbackCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);
        createTestMigration($tempDir, 'a_create_users_table', 'users');
        createTestMigration($tempDir, 'b_create_posts_table', 'posts');

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        expect(await(schema()->hasTable('users')))->toBeTruthy();
        expect(await(schema()->hasTable('posts')))->toBeTruthy();

        $tester = new CommandTester($rollbackCommand);
        $exitCode = $tester->execute([]);

        if ($exitCode !== 0 || await(schema()->hasTable('posts'))) {
            echo "\nRollback Command Failed with Output:\n" . $tester->getDisplay() . "\n";
        }

        expect($exitCode)->toBe(0);
        expect(await(schema()->hasTable('posts')))->toBeFalsy();
        expect(await(schema()->hasTable('users')))->toBeTruthy();
        $records = await(DB::table('migrations')->get());
        expect($records)->toHaveCount(1)
            ->and($records[0]->migration)->toContain('a_create_users_table')
        ;
    });

    it('rolls back multiple migrations when step option is specified', function () use ($tempDir) {
        $application = new Application();
        $rollbackCommand = new MigrateRollbackCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($rollbackCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($rollbackCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'a_create_users_table', 'users');
        createTestMigration($tempDir, 'b_create_posts_table', 'posts');

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);
        $tester = new CommandTester($rollbackCommand);
        $exitCode = $tester->execute(['--step' => 2]);

        expect($exitCode)->toBe(0);

        expect(await(schema()->hasTable('posts')))->toBeFalsy();
        expect(await(schema()->hasTable('users')))->toBeFalsy();

        $count = await(DB::table('migrations')->count());
        expect($count)->toBe(0);
    });

    it('only rolls back migrations matching the specified path option', function () use ($tempDir) {
        $application = new Application();
        $rollbackCommand = new MigrateRollbackCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($rollbackCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($rollbackCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        createTestMigration($tempDir, 'create_temp_table', 'temp_table');

        $timestamp = date('Y_m_d_His', time() + 5);
        $fileName = "{$timestamp}_create_new_name_table.php";
        $content = "<?php
use Hibla\SchemaManager\Schema\Blueprint;
use Hibla\SchemaManager\Schema\Migration;
use function Hibla\await;

return new class extends Migration {
    public function up(): void {
        await(\$this->create('new_name', function (Blueprint \$table) {
            \$table->id();
        }));
    }
    public function down(): void {
        await(\$this->dropIfExists('new_name'));
    }
};";
        $customDir = $tempDir . '/database/migrations/custom';
        mkdir($customDir, 0755, true);
        file_put_contents($customDir . '/' . $fileName, $content);

        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        expect(await(schema()->hasTable('temp_table')))->toBeTruthy();
        expect(await(schema()->hasTable('new_name')))->toBeTruthy();

        $tester = new CommandTester($rollbackCommand);
        $exitCode = $tester->execute([
            '--path' => 'custom',
        ]);

        expect($exitCode)->toBe(0);

        expect(await(schema()->hasTable('new_name')))->toBeFalsy();
        expect(await(schema()->hasTable('temp_table')))->toBeTruthy();
    });

    it('safely handles missing migration files by removing them from repository', function () use ($tempDir) {
        $application = new Application();
        $rollbackCommand = new MigrateRollbackCommand();
        $migrateCommand = new MigrateCommand();

        $application->addCommand($rollbackCommand);
        $application->addCommand($migrateCommand);

        setPrivateProperty($rollbackCommand, 'projectRoot', $tempDir);
        setPrivateProperty($migrateCommand, 'projectRoot', $tempDir);

        $file = createTestMigration($tempDir, 'create_profiles_table', 'profiles');
        $migrateTester = new CommandTester($migrateCommand);
        $migrateTester->execute([]);

        expect(await(schema()->hasTable('profiles')))->toBeTruthy();

        $fullPath = $tempDir . '/database/migrations/' . $file;
        expect(file_exists($fullPath))->toBeTrue();
        unlink($fullPath);

        $tester = new CommandTester($rollbackCommand);
        $exitCode = $tester->execute([]);

        expect($exitCode)->toBe(0);

        $output = $tester->getDisplay();
        expect($output)->toContain('Migration file not found but removed from repository');

        $count = await(DB::table('migrations')->count());
        expect($count)->toBe(0);
    });
});
