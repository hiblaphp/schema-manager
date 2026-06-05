<?php

declare(strict_types=1);

use Hibla\Migrations\Schema\SchemaBuilder;
use Hibla\QueryBuilder\DB;
use Tests\Helpers\SchemaTestHelper;

function driver(): string
{
    return strtolower(getenv('DATABASE') ?: 'mysql');
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

function cleanupSchema(): void
{
    try {
        SchemaTestHelper::cleanupTables(schema());
    } catch (\Throwable $e) {
        // Ignore errors during cleanup
    }

    SchemaTestHelper::closeActiveClient();
    DB::reset();
}