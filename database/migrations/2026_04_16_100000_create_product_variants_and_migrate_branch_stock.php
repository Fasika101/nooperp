<?php

use App\Support\Migration\DropsForeignKeysSafely;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use DropsForeignKeysSafely;

    protected const VARIANT_UNIQUE = 'product_variants_product_color_size_unique';

    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('color_option_id')->nullable()->constrained('product_options')->nullOnDelete();
                $table->foreignId('size_option_id')->nullable()->constrained('product_options')->nullOnDelete();
                $table->timestamps();
            });
        }

        $this->ensureProductVariantsTableIsInnoDb();

        $this->ensureProductVariantsUniqueIndex();

        if (! Schema::hasColumn('branch_product_stocks', 'product_variant_id')) {
            Schema::table('branch_product_stocks', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('branch_id')->constrained('product_variants')->nullOnDelete();
            });
        }

        $this->backfillVariantsAndLinkBranchStocks();

        $this->mergeDuplicateBranchStockRows();

        $this->finalizeBranchProductStocks();

        $this->migrateStockPurchasesToVariants();

        $this->migrateBranchStockTransfersToVariants();
    }

    protected function ensureProductVariantsTableIsInnoDb(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $engine = DB::selectOne(
            'SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            ['product_variants']
        );
        $name = is_object($engine) ? ($engine->ENGINE ?? null) : null;
        if ($name !== null && strtoupper((string) $name) === 'INNODB') {
            return;
        }

        DB::statement('ALTER TABLE `product_variants` ENGINE=InnoDB');
    }

    /**
     * MySQL/MariaDB: expression-only unique indexes are not portable (MariaDB 10.11 rejects some
     * functional-index DDL). Use STORED generated columns + a normal unique index instead.
     */
    protected function ensureMysqlMariaDbProductVariantUniquenessKeyColumns(): void
    {
        if (! Schema::hasColumn('product_variants', 'color_option_key')) {
            DB::statement(
                'ALTER TABLE `product_variants` ADD COLUMN `color_option_key` BIGINT UNSIGNED '
                .'GENERATED ALWAYS AS (IFNULL(`color_option_id`, 0)) STORED'
            );
        }

        if (! Schema::hasColumn('product_variants', 'size_option_key')) {
            DB::statement(
                'ALTER TABLE `product_variants` ADD COLUMN `size_option_key` BIGINT UNSIGNED '
                .'GENERATED ALWAYS AS (IFNULL(`size_option_id`, 0)) STORED'
            );
        }
    }

    protected function ensureProductVariantsUniqueIndex(): void
    {
        if ($this->productVariantsUniqueIndexExists()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->ensureMysqlMariaDbProductVariantUniquenessKeyColumns();

            DB::statement(
                'CREATE UNIQUE INDEX `'.self::VARIANT_UNIQUE.'` ON `product_variants` '
                .'(`product_id`, `color_option_key`, `size_option_key`)'
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX "'.self::VARIANT_UNIQUE.'" ON "product_variants" '
                .'(product_id, COALESCE(color_option_id, 0), COALESCE(size_option_id, 0))'
            );

            return;
        }

        // SQLite and others: expression index (IFNULL is supported).
        DB::statement(
            'CREATE UNIQUE INDEX '.self::VARIANT_UNIQUE.' ON product_variants '
            .'(product_id, IFNULL(color_option_id, 0), IFNULL(size_option_id, 0))'
        );
    }

    protected function productVariantsUniqueIndexExists(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() '
                .'AND table_name = ? AND index_name = ? LIMIT 1',
                ['product_variants', self::VARIANT_UNIQUE]
            );

            return $rows !== [];
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                ['product_variants', self::VARIANT_UNIQUE]
            );

            return $rows !== [];
        }

        $indexes = DB::select('PRAGMA index_list(product_variants)');
        foreach ($indexes as $idx) {
            $name = $idx->name ?? $idx->Name ?? null;
            if ($name === self::VARIANT_UNIQUE) {
                return true;
            }
        }

        return false;
    }

    protected function backfillVariantsAndLinkBranchStocks(): void
    {
        $hasColor = Schema::hasColumn('branch_product_stocks', 'color_option_id');

        $select = ['id', 'branch_id', 'product_id', 'quantity', 'avg_cost'];
        if ($hasColor) {
            $select[] = 'color_option_id';
        }

        $rows = DB::table('branch_product_stocks')->select($select)->get();

        foreach ($rows as $row) {
            $colorId = $hasColor ? ($row->color_option_id ?? null) : null;
            if ($colorId !== null) {
                $colorId = (int) $colorId;
                if ($colorId <= 0) {
                    $colorId = null;
                }
            }

            $variantId = $this->findOrCreateVariantId((int) $row->product_id, $colorId, null);

            DB::table('branch_product_stocks')->where('id', $row->id)->update([
                'product_variant_id' => $variantId,
            ]);
        }
    }

    protected function findOrCreateVariantId(int $productId, ?int $colorOptionId, ?int $sizeOptionId): int
    {
        $q = DB::table('product_variants')->where('product_id', $productId);
        if ($colorOptionId !== null) {
            $q->where('color_option_id', $colorOptionId);
        } else {
            $q->whereNull('color_option_id');
        }
        if ($sizeOptionId !== null) {
            $q->where('size_option_id', $sizeOptionId);
        } else {
            $q->whereNull('size_option_id');
        }

        $existing = $q->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'color_option_id' => $colorOptionId,
            'size_option_id' => $sizeOptionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function mergeDuplicateBranchStockRows(): void
    {
        $dupes = DB::table('branch_product_stocks')
            ->select('branch_id', 'product_variant_id', DB::raw('MIN(id) as keep_id'), DB::raw('SUM(quantity) as total_qty'))
            ->whereNotNull('product_variant_id')
            ->groupBy('branch_id', 'product_variant_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            $rows = DB::table('branch_product_stocks')
                ->where('branch_id', $dupe->branch_id)
                ->where('product_variant_id', $dupe->product_variant_id)
                ->orderBy('id')
                ->get();

            $first = $rows->first();
            $sumQty = (int) $rows->sum('quantity');
            $avgCosts = $rows->pluck('avg_cost')->filter(fn ($v) => $v !== null && $v !== '');
            $avgCost = $avgCosts->isNotEmpty() ? (float) $avgCosts->first() : null;

            DB::table('branch_product_stocks')->where('id', $first->id)->update([
                'quantity' => $sumQty,
                'avg_cost' => $avgCost,
            ]);

            $idsToDelete = $rows->pluck('id')->slice(1)->all();
            if ($idsToDelete !== []) {
                DB::table('branch_product_stocks')->whereIn('id', $idsToDelete)->delete();
            }
        }
    }

    protected function finalizeBranchProductStocks(): void
    {
        $this->dropBranchProductStocksOldUniqueIndexes();

        $this->dropForeignKeyIfExists('branch_product_stocks', 'product_id');
        if (Schema::hasColumn('branch_product_stocks', 'color_option_id')) {
            $this->dropForeignKeyIfExists('branch_product_stocks', 'color_option_id');
        }

        Schema::table('branch_product_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('branch_product_stocks', 'color_option_id')) {
                $table->dropColumn('color_option_id');
            }
            if (Schema::hasColumn('branch_product_stocks', 'product_id')) {
                $table->dropColumn('product_id');
            }
        });

        Schema::table('branch_product_stocks', function (Blueprint $table) {
            $table->unique(['branch_id', 'product_variant_id']);
        });
    }

    protected function dropBranchProductStocksOldUniqueIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach ([
                'branch_product_stocks_branch_product_color_unique',
                'branch_product_stocks_branch_id_product_id_unique',
            ] as $idx) {
                try {
                    DB::statement('DROP INDEX IF EXISTS '.$idx);
                } catch (Throwable) {
                }
            }

            return;
        }

        if ($driver === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM branch_product_stocks');
            $names = collect($indexes)->pluck('Key_name')->unique()->filter(fn ($n) => $n !== 'PRIMARY');
            foreach ($names as $name) {
                try {
                    DB::statement('ALTER TABLE branch_product_stocks DROP INDEX `'.$name.'`');
                } catch (Throwable) {
                }
            }
        }
    }

    protected function migrateStockPurchasesToVariants(): void
    {
        if (! Schema::hasColumn('stock_purchases', 'product_variant_id')) {
            Schema::table('stock_purchases', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            });
        }

        $hasColor = Schema::hasColumn('stock_purchases', 'color_option_id');
        $purchases = DB::table('stock_purchases')->select('id', 'product_id')->get();

        foreach ($purchases as $p) {
            $colorId = $hasColor
                ? DB::table('stock_purchases')->where('id', $p->id)->value('color_option_id')
                : null;
            if ($colorId !== null) {
                $colorId = (int) $colorId;
                if ($colorId <= 0) {
                    $colorId = null;
                }
            }

            $vid = $this->findOrCreateVariantId((int) $p->product_id, $colorId, null);
            DB::table('stock_purchases')->where('id', $p->id)->update(['product_variant_id' => $vid]);
        }

        $this->dropForeignKeyIfExists('stock_purchases', 'color_option_id');
        if (Schema::hasColumn('stock_purchases', 'color_option_id')) {
            Schema::table('stock_purchases', function (Blueprint $table) {
                $table->dropColumn('color_option_id');
            });
        }

        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable(false)->change();
        });
    }

    protected function migrateBranchStockTransfersToVariants(): void
    {
        if (! Schema::hasColumn('branch_stock_transfers', 'product_variant_id')) {
            Schema::table('branch_stock_transfers', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            });
        }

        $hasColor = Schema::hasColumn('branch_stock_transfers', 'color_option_id');
        $rows = DB::table('branch_stock_transfers')->select('id', 'product_id')->get();

        foreach ($rows as $row) {
            $colorId = $hasColor
                ? DB::table('branch_stock_transfers')->where('id', $row->id)->value('color_option_id')
                : null;
            if ($colorId !== null) {
                $colorId = (int) $colorId;
                if ($colorId <= 0) {
                    $colorId = null;
                }
            }

            $vid = $this->findOrCreateVariantId((int) $row->product_id, $colorId, null);
            DB::table('branch_stock_transfers')->where('id', $row->id)->update(['product_variant_id' => $vid]);
        }

        $this->dropForeignKeyIfExists('branch_stock_transfers', 'color_option_id');
        if (Schema::hasColumn('branch_stock_transfers', 'color_option_id')) {
            Schema::table('branch_stock_transfers', function (Blueprint $table) {
                $table->dropColumn('color_option_id');
            });
        }

        Schema::table('branch_stock_transfers', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Complex rollback omitted for safety; restore from backup if needed.
    }
};
