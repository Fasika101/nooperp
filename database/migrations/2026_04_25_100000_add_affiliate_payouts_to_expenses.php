<?php

use App\Models\ExpenseType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('expense_types')->where('name', ExpenseType::NAME_AFFILIATE_PAYOUT)->exists()) {
            DB::table('expense_types')->insert([
                'name' => ExpenseType::NAME_AFFILIATE_PAYOUT,
                'is_active' => true,
                'is_recurring' => false,
                'frequency' => null,
                'day_of_month' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('affiliate_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('affiliates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_id');
        });
    }
};
