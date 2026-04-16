<?php

use App\Support\Migration\DropsForeignKeysSafely;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair migration: an earlier version of 2026_04_13_200000 returned early when
 * branch_product_stocks.color_option_id already existed, so stock_purchases /
 * branch_stock_transfers never received color_option_id. This migration adds
 * those columns if they are still missing (safe to run multiple times).
 */
return new class extends Migration
{
    use DropsForeignKeysSafely;

    public function up(): void
    {
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

    public function down(): void
    {
        $this->dropForeignKeyIfExists('branch_stock_transfers', 'color_option_id');
        if (Schema::hasColumn('branch_stock_transfers', 'color_option_id')) {
            Schema::table('branch_stock_transfers', function (Blueprint $table) {
                $table->dropColumn('color_option_id');
            });
        }

        $this->dropForeignKeyIfExists('stock_purchases', 'color_option_id');
        if (Schema::hasColumn('stock_purchases', 'color_option_id')) {
            Schema::table('stock_purchases', function (Blueprint $table) {
                $table->dropColumn('color_option_id');
            });
        }
    }
};
