<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const BRANCH_STOCK_COLOR_UNIQUE = 'branch_product_stocks_branch_product_color_unique';

    public function up(): void
    {
        // Do not bail out when only branch_product_stocks was migrated earlier; still add columns
        // to stock_purchases and branch_stock_transfers if they are missing.

        if (! Schema::hasColumn('branch_product_stocks', 'color_option_id')) {
            $this->dropBranchProductStocksBranchProductUniqueIfExists();

            Schema::table('branch_product_stocks', function (Blueprint $table) {
                $table->foreignId('color_option_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_options')
                    ->nullOnDelete();
            });

            $this->backfillBranchProductStockColors();

            $this->addBranchProductStocksColorUniqueIndexIfMissing();
        }

        if (! Schema::hasColumn('stock_purchases', 'color_option_id')) {
            Schema::table('stock_purchases', function (Blueprint $table) {
                $table->foreignId('color_option_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_options')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('branch_stock_transfers', 'color_option_id')) {
            Schema::table('branch_stock_transfers', function (Blueprint $table) {
                $table->foreignId('color_option_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_options')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Drop the legacy unique index on (branch_id, product_id). MySQL index names can differ from
     * Laravel's default after imports or older migrations, so we resolve the actual name from SHOW INDEX.
     */
    protected function dropBranchProductStocksBranchProductUniqueIfExists(): void
    {
        $table = 'branch_product_stocks';
        $expected = ['branch_id', 'product_id'];
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM `'.$table.'`');
            $byKey = [];
            foreach ($indexes as $row) {
                $r = (array) $row;
                $keyName = $r['Key_name'] ?? $r['key_name'] ?? null;
                if ($keyName === null || $keyName === 'PRIMARY') {
                    continue;
                }
                $byKey[$keyName][] = $r;
            }
            foreach ($byKey as $keyName => $rows) {
                $first = $rows[0];
                $nonUnique = (int) ($first['Non_unique'] ?? $first['non_unique'] ?? 1);
                if ($nonUnique !== 0) {
                    continue;
                }
                usort($rows, fn (array $a, array $b): int => ((int) ($a['Seq_in_index'] ?? $a['seq_in_index'] ?? 0))
                    <=> ((int) ($b['Seq_in_index'] ?? $b['seq_in_index'] ?? 0)));
                $cols = array_map(fn (array $r) => $r['Column_name'] ?? $r['column_name'] ?? '', $rows);
                if ($cols === $expected) {
                    DB::statement('ALTER TABLE `'.$table.'` DROP INDEX `'.$keyName.'`');

                    return;
                }
            }

            return;
        }

        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['branch_id', 'product_id']);
            });
        } catch (\Throwable) {
            // Index missing or name mismatch; safe to continue if column add will fail otherwise.
        }
    }

    protected function backfillBranchProductStockColors(): void
    {
        $rows = DB::table('branch_product_stocks')->select('id', 'product_id')->get();

        foreach ($rows as $row) {
            $firstColorId = DB::table('product_product_option')
                ->join('product_options', 'product_options.id', '=', 'product_product_option.product_option_id')
                ->where('product_product_option.product_id', $row->product_id)
                ->where('product_options.type', 'color')
                ->orderBy('product_options.name')
                ->value('product_options.id');

            DB::table('branch_product_stocks')
                ->where('id', $row->id)
                ->update(['color_option_id' => $firstColorId]);
        }
    }

    protected function addBranchProductStocksColorUniqueIndexIfMissing(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $exists = DB::selectOne(
                'SELECT 1 AS ok FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                ['branch_product_stocks', self::BRANCH_STOCK_COLOR_UNIQUE],
            );
            if ($exists) {
                return;
            }
        } elseif ($driver === 'sqlite') {
            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [self::BRANCH_STOCK_COLOR_UNIQUE]);
            if ($indexes !== []) {
                return;
            }
        }

        $expr = $driver === 'pgsql'
            ? 'COALESCE(color_option_id, 0)'
            : 'IFNULL(color_option_id, 0)';

        DB::statement(
            'CREATE UNIQUE INDEX '.self::BRANCH_STOCK_COLOR_UNIQUE
                .' ON branch_product_stocks (branch_id, product_id, '.$expr.')'
        );
    }

    public function down(): void
    {
        Schema::table('branch_stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['color_option_id']);
            $table->dropColumn('color_option_id');
        });

        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->dropForeign(['color_option_id']);
            $table->dropColumn('color_option_id');
        });

        $this->dropBranchProductStocksColorUniqueIndex();

        Schema::table('branch_product_stocks', function (Blueprint $table) {
            $table->dropForeign(['color_option_id']);
            $table->dropColumn('color_option_id');
        });

        Schema::table('branch_product_stocks', function (Blueprint $table) {
            $table->unique(['branch_id', 'product_id']);
        });
    }

    protected function dropBranchProductStocksColorUniqueIndex(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS '.self::BRANCH_STOCK_COLOR_UNIQUE);

            return;
        }

        Schema::table('branch_product_stocks', function (Blueprint $table) {
            $table->dropIndex(self::BRANCH_STOCK_COLOR_UNIQUE);
        });
    }
};
