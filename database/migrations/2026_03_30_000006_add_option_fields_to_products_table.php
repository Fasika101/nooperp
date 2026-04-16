<?php

use App\Support\Migration\DropsForeignKeysSafely;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use DropsForeignKeysSafely;

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('size_option_id')->nullable()->after('category_id')->constrained('product_options')->nullOnDelete();
            $table->foreignId('color_option_id')->nullable()->after('size_option_id')->constrained('product_options')->nullOnDelete();
            $table->foreignId('gender_option_id')->nullable()->after('color_option_id')->constrained('product_options')->nullOnDelete();
            $table->foreignId('material_option_id')->nullable()->after('gender_option_id')->constrained('product_options')->nullOnDelete();
            $table->foreignId('shape_option_id')->nullable()->after('material_option_id')->constrained('product_options')->nullOnDelete();
            $table->foreignId('brand_option_id')->nullable()->after('shape_option_id')->constrained('product_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['size_option_id', 'color_option_id', 'gender_option_id', 'material_option_id', 'shape_option_id', 'brand_option_id'] as $col) {
            $this->dropForeignKeyIfExists('products', $col);
        }
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'size_option_id',
                'color_option_id',
                'gender_option_id',
                'material_option_id',
                'shape_option_id',
                'brand_option_id',
            ]);
        });
    }
};
