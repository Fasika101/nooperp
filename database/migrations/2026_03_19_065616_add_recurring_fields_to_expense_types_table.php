<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_types', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('is_active');
            $table->string('frequency', 20)->nullable()->after('is_recurring'); // weekly, monthly, yearly
            $table->unsignedTinyInteger('day_of_month')->nullable()->after('frequency'); // 1-31 for monthly
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_types', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'frequency', 'day_of_month']);
        });
    }
};
