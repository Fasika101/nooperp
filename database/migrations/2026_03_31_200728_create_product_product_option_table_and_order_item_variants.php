<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_product_option', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_option_id']);
        });

        foreach (DB::table('products')->select('id', 'size_option_id', 'color_option_id')->get() as $row) {
            $now = now();
            if ($row->size_option_id) {
                DB::table('product_product_option')->insertOrIgnore([
                    'product_id' => $row->id,
                    'product_option_id' => $row->size_option_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if ($row->color_option_id) {
                DB::table('product_product_option')->insertOrIgnore([
                    'product_id' => $row->id,
                    'product_option_id' => $row->color_option_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('size_option_id')->nullable()->after('product_id')->constrained('product_options')->nullOnDelete();
            $table->foreignId('color_option_id')->nullable()->after('size_option_id')->constrained('product_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['size_option_id']);
            $table->dropForeign(['color_option_id']);
            $table->dropColumn(['size_option_id', 'color_option_id']);
        });

        Schema::dropIfExists('product_product_option');
    }
};
