<?php

declare(strict_types=1);

namespace App\Support\Migration;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel runs Blueprint SQL after the closure returns, so try/catch inside
 * Schema::table() often misses QueryException on DROP FOREIGN KEY. These helpers
 * look up real constraint names (MySQL/MariaDB, PostgreSQL) or wrap SQLite safely.
 */
trait DropsForeignKeysSafely
{
    protected function dropForeignKeyIfExists(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $prefixed = $this->migrationTableName($table);
            $name = $this->findMysqlForeignKeyName($prefixed, $column);
            if ($name === null) {
                return;
            }
            try {
                DB::statement('ALTER TABLE `'.$prefixed.'` DROP FOREIGN KEY `'.$name.'`');
            } catch (\Throwable) {
            }

            return;
        }

        if ($driver === 'pgsql') {
            $name = $this->findPgsqlForeignKeyName($table, $column);
            if ($name === null) {
                return;
            }
            try {
                DB::statement('ALTER TABLE "'.$table.'" DROP CONSTRAINT "'.$name.'"');
            } catch (\Throwable) {
            }

            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
        }
    }

    /**
     * Drop a named non-primary index if it exists (MySQL/MariaDB/PostgreSQL/SQLite fallbacks).
     */
    protected function dropIndexIfExists(string $table, string $indexName): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $prefixed = $this->migrationTableName($table);

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT 1 AS ok FROM information_schema.statistics WHERE table_schema = DATABASE() '
                .'AND table_name = ? AND index_name = ? LIMIT 1',
                [$prefixed, $indexName]
            );
            if ($row === null) {
                return;
            }
            try {
                DB::statement('ALTER TABLE `'.$prefixed.'` DROP INDEX `'.$indexName.'`');
            } catch (\Throwable) {
            }

            return;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ? LIMIT 1',
                [$table, $indexName]
            );
            if ($row === null) {
                return;
            }
            try {
                DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
            } catch (\Throwable) {
            }

            return;
        }

        try {
            DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                    $blueprint->dropIndex($indexName);
                });
            } catch (\Throwable) {
            }
        }
    }

    protected function migrationTableName(string $table): string
    {
        return Schema::getConnection()->getTablePrefix().$table;
    }

    protected function findMysqlForeignKeyName(string $prefixedTableName, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT kcu.CONSTRAINT_NAME AS constraint_name FROM information_schema.KEY_COLUMN_USAGE kcu '
            .'INNER JOIN information_schema.TABLE_CONSTRAINTS tc '
            .'ON tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND tc.TABLE_NAME = kcu.TABLE_NAME '
            .'AND tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME '
            .'WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.TABLE_NAME = ? AND kcu.COLUMN_NAME = ? '
            .'AND tc.CONSTRAINT_TYPE = ? LIMIT 1',
            [$prefixedTableName, $column, 'FOREIGN KEY']
        );

        if ($row === null) {
            return null;
        }

        $arr = (array) $row;

        return $arr['constraint_name'] ?? null;
    }

    protected function findPgsqlForeignKeyName(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT tc.constraint_name AS constraint_name FROM information_schema.table_constraints tc '
            .'INNER JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name '
            .'AND tc.table_schema = kcu.table_schema AND tc.table_name = kcu.table_name '
            .'WHERE tc.table_schema = current_schema() AND tc.table_name = ? AND kcu.column_name = ? '
            .'AND tc.constraint_type = ? LIMIT 1',
            [$table, $column, 'FOREIGN KEY']
        );

        if ($row === null) {
            return null;
        }

        $arr = (array) $row;

        return $arr['constraint_name'] ?? null;
    }
}
