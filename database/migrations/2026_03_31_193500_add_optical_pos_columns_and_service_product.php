<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('stock');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('line_label')->nullable()->after('product_id');
            $table->json('optical_meta')->nullable()->after('unit_cost');
        });

        $now = now();
        $categoryId = DB::table('categories')->where('name', 'Optical services')->value('id');
        if (! $categoryId) {
            $categoryId = DB::table('categories')->insertGetId([
                'name' => 'Optical services',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('products')->where('name', 'POS — Optical lens line')->exists()) {
            DB::table('products')->insert([
                'name' => 'POS — Optical lens line',
                'category_id' => $categoryId,
                'size_option_id' => null,
                'color_option_id' => null,
                'gender_option_id' => null,
                'material_option_id' => null,
                'shape_option_id' => null,
                'brand_option_id' => null,
                'price' => 0,
                'original_price' => null,
                'cost_price' => null,
                'stock' => 0,
                'is_service' => true,
                'image' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('products')
                ->where('name', 'POS — Optical lens line')
                ->update(['is_service' => true]);
        }
    }

    public function down(): void
    {
        DB::table('products')->where('name', 'POS — Optical lens line')->delete();

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['line_label', 'optical_meta']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_service');
        });
    }
};
