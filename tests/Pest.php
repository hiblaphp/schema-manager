<?php

declare(strict_types=1);

use Hibla\QueryBuilder\DB;
use Hibla\SchemaManager\Schema\SchemaBuilder;
use Rcalicdan\Defer\Defer;
use Tests\Helpers\SchemaTestHelper;

use function Hibla\await;
use function Hibla\sleep;

Defer::global(function () {
    $pid = getmypid();

    if (driver() === 'sqlite') {
        $file = __DIR__ . '/../database_' . $pid . '.sqlite';
        $files = [$file, $file . '-wal', $file . '-shm'];

        foreach ($files as $f) {
            if (file_exists($f)) {
                for ($i = 1; $i <= 10; $i++) {
                    if (@unlink($f)) {
                        $deleted = true;

                        break;
                    }

                    usleep(100000);
                }
            }
        }
    }
});

function driver(): string
{
    return strtolower(getenv('DATABASE') ?: 'sqlite');
}

function schema(?string $driver = null): SchemaBuilder
{
    return SchemaTestHelper::createSchemaBuilder($driver ?? driver());
}

function initializeSchema(): void
{
    SchemaTestHelper::initializeDatabaseForDriver(driver());
    SchemaTestHelper::cleanupTables(schema());
}

function cleanupSchema(?string $driver = null): void
{
    try {
        SchemaTestHelper::cleanupTables(schema($driver));
        await(schema($driver)->dropIfExists('migrations'));
    } catch (Throwable $e) {
        // Ignore errors during cleanup
    }

    SchemaTestHelper::closeActiveClient();
    DB::reset();
}

function setPrivateProperty(object $object, string $propertyName, mixed $value): void
{
    $reflector = new ReflectionClass($object);
    $property = $reflector->getProperty($propertyName);
    $property->setValue($object, $value);
}

function deleteDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

function createTestMigration(string $projectRoot, string $name, string $table, ?string $connection = null): string
{
    $timestamp = date('Y_m_d_His');
    $fileName = "{$timestamp}_{$name}.php";

    $connectionLine = $connection !== null
        ? "    protected ?string \$connection = '{$connection}';\n\n"
        : '';

    $content = "<?php
use Hibla\SchemaManager\Schema\Blueprint;
use Hibla\SchemaManager\Schema\Migration;
use function Hibla\await;

return new class extends Migration {
{$connectionLine}    public function up(): void {
        await(\$this->create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
        }));
    }
    public function down(): void {
        await(\$this->dropIfExists('{$table}'));
    }
};";

    $dir = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    sleep(0.1);

    file_put_contents($dir . DIRECTORY_SEPARATOR . $fileName, $content);

    return $fileName;
}
