<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\Utilities\ConnectionFactory;
use Hibla\SchemaManager\Schema\SchemaBuilder;
use Hibla\Sql\SqlClientInterface;

use function Hibla\await;

class SchemaTestHelper
{
    private static ?SqlClientInterface $activeClient = null;

    private static array $testTables = [
        'users',
        'posts',
        'comments',
        'categories',
        'tags',
        'profiles',
        'articles',
        'locations',
        'stats',
        'documents',
        'financials',
        'events',
        'orders',
        'settings',
        'temp_table',
        'old_name',
        'new_name',
        'counters',
        'logs',
        'products',
        'empty_table',
        'wide_table',
        'user_roles',
        'user_profiles',
    ];

    /**
     * Initialize database with specific driver configuration from environment variables.
     */
    public static function initializeDatabaseForDriver(string $driver): void
    {
        self::closeActiveClient();
        DB::reset();

        $config = self::getDriverConfig($driver);

        $_ENV['DB_CONNECTION'] = $driver;
        $_ENV['DB_HOST'] = $config['host'] ?? '127.0.0.1';
        $_ENV['DB_PORT'] = (string) ($config['port'] ?? 3306);
        $_ENV['DB_DATABASE'] = $config['database'];
        $_ENV['DB_USERNAME'] = $config['username'] ?? 'root';
        $_ENV['DB_PASSWORD'] = $config['password'] ?? '';

        $_SERVER['DB_CONNECTION'] = $driver;
        $_SERVER['DB_HOST'] = $config['host'] ?? '127.0.0.1';
        $_SERVER['DB_PORT'] = (string) ($config['port'] ?? 3306);
        $_SERVER['DB_DATABASE'] = $config['database'];
        $_SERVER['DB_USERNAME'] = $config['username'] ?? 'root';
        $_SERVER['DB_PASSWORD'] = $config['password'] ?? '';

        self::$activeClient = ConnectionFactory::make($config);

        $driverEnum = match ($driver) {
            'pgsql' => DatabaseDriver::Postgres,
            'sqlite' => DatabaseDriver::Sqlite,
            default => DatabaseDriver::Mysql,
        };

        DB::setSqlClient(self::$activeClient, $driverEnum);

        await(DB::rawExecute('SELECT 1'));
    }

    /**
     * Gracefully close the active database pool if one is running.
     */
    public static function closeActiveClient(): void
    {
        if (self::$activeClient !== null) {
            self::$activeClient->close();
            self::$activeClient = null;
        }
    }

    /**
     * Get configuration array for specific driver from environment variables.
     *
     * @return array<string, mixed>
     */
    private static function getDriverConfig(string $driver): array
    {
        return match ($driver) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => $_ENV['MYSQL_HOST'] ?? '127.0.0.1',
                'port' => (int) ($_ENV['MYSQL_PORT'] ?? 3306),
                'database' => $_ENV['MYSQL_DATABASE'] ?? 'test_db',
                'username' => $_ENV['MYSQL_USERNAME'] ?? 'test_user',
                'password' => $_ENV['MYSQL_PASSWORD'] ?? 'test_password',
                'charset' => 'utf8mb4',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => $_ENV['PGSQL_HOST'] ?? '127.0.0.1',
                'port' => (int) ($_ENV['PGSQL_PORT'] ?? 5443),
                'database' => $_ENV['PGSQL_DATABASE'] ?? 'test_db',
                'username' => $_ENV['PGSQL_USERNAME'] ?? 'postgres',
                'password' => $_ENV['PGSQL_PASSWORD'] ?? 'postgres',
            ],
            'sqlite' => [ 
                'driver' => 'sqlite',
                'database' => __DIR__ . '/../../database_' . getmypid() . '.sqlite',
                'max_connections' => 2,
                'min_connections' => 1,
            ],
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}"),
        };
    }

    public static function createSchemaBuilder(?string $driver = null): SchemaBuilder
    {
        return new SchemaBuilder($driver);
    }

    public static function cleanupTables(SchemaBuilder $schema): void
    {
        foreach (self::$testTables as $table) {
            try {
                await($schema->dropIfExists($table));
            } catch (\Throwable $e) {
                // Ignore if table doesn't exist
            }
        }
    }

    public static function getTestTables(): array
    {
        return self::$testTables;
    }
}
