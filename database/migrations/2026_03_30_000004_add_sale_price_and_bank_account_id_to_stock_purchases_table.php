<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->decimal('sale_price', 12, 2)->nullable()->after('unit_cost');
            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('date')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['sale_price', 'bank_account_id']);
        });
    }
};
