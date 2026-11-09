<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Binocular PD range (mm) this frame fits well. Used by the shop virtual
     * try-on to tell the customer "fits / too big / too small" after measuring
     * their PD in the browser. Both nullable: when unset no fit verdict is shown.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('pd_fit_min_mm', 5, 1)->nullable()->after('deepar_effect');
            $table->decimal('pd_fit_max_mm', 5, 1)->nullable()->after('pd_fit_min_mm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pd_fit_min_mm', 'pd_fit_max_mm']);
        });
    }
};
