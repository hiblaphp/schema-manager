<?php

declare(strict_types=1);

namespace Hibla\SchemaManager\Schema;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\DB;
use Hibla\QueryBuilder\Interfaces\BaseQueryBuilderInterface;
use Hibla\QueryBuilder\Utilities\ConfigResolver;

use function Hibla\async;

/**
 * Base Seeder class providing async database access and seeder orchestration.
 */
abstract class Seeder
{
    /**
     * The database connection to use for this seeder.
     * If null, uses the default connection.
     */
    protected ?string $connection = null;

    /**
     * Run the database seeds.
     */
    abstract public function run(): void;

    /**
     * Get the database connection for this seeder.
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the database connection for this seeder.
     */
    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Get a query builder instance for a specific table.
     */
    protected function db(string $table): BaseQueryBuilderInterface
    {
        return DB::connection($this->connection)->table($table);
    }

    /**
     * Seed another seeder class or file testing-isolation safely.
     *
     * @param string|Seeder $seeder The seeder relative path, class-string, or an instantiated Seeder object.
     *
     * @return PromiseInterface<void>
     */
    protected function call(string|Seeder $seeder): PromiseInterface
    {
        return async(function () use ($seeder) {
            if ($seeder instanceof Seeder) {
                if ($seeder->getConnection() === null) {
                    $seeder->setConnection($this->connection);
                }
                $seeder->run();

                return;
            }

            if (class_exists($seeder) && is_subclass_of($seeder, self::class)) {
                /** @var Seeder $seederInstance */
                $seederInstance = new $seeder();
                if ($seederInstance->getConnection() === null) {
                    $seederInstance->setConnection($this->connection);
                }
                $seederInstance->run();

                return;
            }

            $config = ConfigResolver::getSeedersConfig();

            $basePath = \is_string($config['seeds_path'] ?? null)
                ? $config['seeds_path']
                : './database/seeders';

            $filePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . ltrim($seeder, '/\\');

            if (! str_ends_with($filePath, '.php')) {
                $filePath .= '.php';
            }

            if (! file_exists($filePath)) {
                throw new \RuntimeException("Seeder file not found: {$filePath}");
            }

            $seederInstance = require $filePath;

            if (! $seederInstance instanceof Seeder) {
                throw new \RuntimeException("Seeder file '{$seeder}' must return an instance of Hibla\\Migrations\\Schema\\Seeder.");
            }

            if ($seederInstance->getConnection() === null) {
                $seederInstance->setConnection($this->connection);
            }

            $seederInstance->run();
        });
    }
}
