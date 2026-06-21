<?php

declare(strict_types=1);

namespace Hibla\SchemaManager\Schema;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\RawQueryInterface;
use Hibla\QueryBuilder\Interfaces\TransactionalQueryBuilderInterface;

use function Hibla\async;
use function Hibla\await;

class SQLiteSchemaBuilder
{
    private SchemaCompiler $compiler;

    public function __construct(SchemaCompiler $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Handle CREATE TABLE for SQLite.
     *
     * @param string $sql
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<int|null>
     */
    public function handleCreate(string $sql, RawQueryInterface $client): PromiseInterface
    {
        return async(function () use ($sql, $client) {
            await($client->rawExecute('PRAGMA foreign_keys = ON', []));

            return await($client->rawExecute($sql, []));
        });
    }

    /**
     * Handle table modification for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<int|list<int>|null|bool>
     */
    public function handleTable(string $table, Blueprint $blueprint, RawQueryInterface $client): PromiseInterface
    {
        $needsRecreation = \count($blueprint->getDropColumns()) > 0 ||
            \count($blueprint->getModifyColumns()) > 0 ||
            \count($blueprint->getDropForeignKeys()) > 0 ||
            \count($blueprint->getDropIndexes()) > 0;

        if (! $needsRecreation) {
            return $this->executeAlter($blueprint, $client);
        }

        return $this->handleTableRecreation($table, $blueprint, $client);
    }

    /**
     * Handle DROP COLUMN for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropColumn(string $table, Blueprint $blueprint, RawQueryInterface $client): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint, $client);
    }

    /**
     * Handle DROP INDEX for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropIndex(string $table, Blueprint $blueprint, RawQueryInterface $client): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint, $client);
    }

    /**
     * Handle DROP FOREIGN KEY for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropForeign(string $table, Blueprint $blueprint, RawQueryInterface $client): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint, $client);
    }

    /**
     * Execute table recreation for schema modifications.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function handleTableRecreation(string $table, Blueprint $blueprint, RawQueryInterface $client): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint, $client);
    }

    /**
     * Execute table recreation.
     * This safely rebuilds a table while preserving existing indexes and avoiding concurrent lock conflicts.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function executeTableRecreation(string $table, Blueprint $blueprint, RawQueryInterface $client): PromiseInterface
    {
        return async(function () use ($table, $blueprint, $client) {
            // Fetch existing column structure
            $existingColumns = await($client->raw("PRAGMA table_info(`{$table}`)", []));

            // Fetch existing indexes so they aren't lost upon table drop
            $indexRows = await($client->raw("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL", [$table]));

            /** @var list<string> $existingIndexesSql */
            $existingIndexesSql = [];
            foreach ($indexRows as $row) {
                $rowArray = (array) $row;
                if (isset($rowArray['sql']) && \is_string($rowArray['sql'])) {
                    $existingIndexesSql[] = $rowArray['sql'];
                }
            }

            if (method_exists($this->compiler, 'setExistingTableColumns')) {
                $this->compiler->setExistingTableColumns($existingColumns);
            }

            $sql = $this->compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                $statements = $sql;
                $dropIndexNames = array_map(fn ($idx) => $idx[0], $blueprint->getDropIndexes());

                // Re-append existing indexes, stripping out any that are explicitly requested to be dropped
                foreach ($existingIndexesSql as $indexSql) {
                    $skip = false;
                    foreach ($dropIndexNames as $dropName) {
                        if (
                            str_contains($indexSql, "`{$dropName}`") ||
                            str_contains($indexSql, "\"{$dropName}\"") ||
                            str_contains($indexSql, " {$dropName} ") ||
                            str_contains($indexSql, "_{$dropName}_")
                        ) {
                            $skip = true;

                            break;
                        }
                    }
                    if (! $skip) {
                        $statements[] = $indexSql;
                    }
                }

                return \count($statements) === 0 ? true : await($this->executeStatements(array_values($statements), $client));
            }

            return await($client->rawExecute($sql, []));
        });
    }

    /**
     * Execute ALTER TABLE statements.
     *
     * @param Blueprint $blueprint
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function executeAlter(Blueprint $blueprint, RawQueryInterface $client): PromiseInterface
    {
        return async(function () use ($blueprint, $client) {
            $sql = $this->compiler->compileAlter($blueprint);

            if (\is_array($sql)) {
                return \count($sql) === 0 ? true : await($this->executeMultiple($sql, $client));
            }

            return await($client->rawExecute($sql, []));
        });
    }

    /**
     * Execute a list of SQL statements atomically.
     * Utilizes PRAGMA defer_foreign_keys to allow table swapping without FK violations.
     *
     * @param list<string> $statements
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<bool>
     */
    private function executeStatements(array $statements, RawQueryInterface $client): PromiseInterface
    {
        return async(function () use ($statements, $client) {
            $runInTx = function (TransactionalQueryBuilderInterface $tx) use ($statements) {
                return async(function () use ($tx, $statements) {
                    // Defer checks gracefully allows the drop + recreate process
                    // without throwing integrity constraint violations mid-transaction.
                    await($tx->rawExecute('PRAGMA defer_foreign_keys = ON', []));

                    foreach ($statements as $statement) {
                        await($tx->rawExecute($statement, []));
                    }

                    return true;
                });
            };

            // If already inside an active transaction, piggyback on it.
            if ($client instanceof TransactionalQueryBuilderInterface) {
                return await($runInTx($client));
            }

            // If it's a root connection, spawn a new transaction from the pool.
            if ($client instanceof DatabaseConnectionInterface) {
                return await($client->transaction(function ($tx) use ($runInTx) {
                    return await($runInTx($tx));
                }));
            }

            // Fallback: Execute without a transaction wrapper if the client is purely raw
            await($client->rawExecute('PRAGMA defer_foreign_keys = ON', []));
            foreach ($statements as $statement) {
                await($client->rawExecute($statement, []));
            }

            return true;
        });
    }

    /**
     * Execute multiple SQL statements and return results.
     *
     * @param list<string> $statements
     * @param RawQueryInterface $client
     *
     * @return PromiseInterface<list<int>>
     */
    private function executeMultiple(array $statements, RawQueryInterface $client): PromiseInterface
    {
        return async(function () use ($statements, $client) {
            $results = [];
            foreach ($statements as $sql) {
                $results[] = await($client->rawExecute($sql, []));
            }

            /** @var list<int> */
            return $results;
        });
    }
}
