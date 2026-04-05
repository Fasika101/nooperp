<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('lens_width_mm', 5, 1)
                ->nullable()
                ->after('brand_option_id')
                ->comment('Frame eye size / lens width');
            $table->decimal('bridge_width_mm', 5, 1)
                ->nullable()
                ->after('lens_width_mm');
            $table->decimal('temple_length_mm', 5, 1)
                ->nullable()
                ->after('bridge_width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'lens_width_mm',
                'bridge_width_mm',
                'temple_length_mm',
            ]);
        });
    }
};
