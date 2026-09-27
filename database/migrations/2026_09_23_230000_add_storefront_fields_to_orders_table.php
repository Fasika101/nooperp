<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source')->default('pos')->after('status');
            $table->string('external_ref')->nullable()->after('source');
            $table->index('external_ref');
        });

        $exists = DB::table('payment_types')
            ->where('name', 'Chapa / Online')
            ->exists();

        if (! $exists) {
            DB::table('payment_types')->insert([
                'name' => 'Chapa / Online',
                'branch_id' => null,
                'is_global' => true,
                'bank_account_id' => null,
                'is_active' => true,
                'is_accounts_receivable' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['external_ref']);
            $table->dropColumn(['source', 'external_ref']);
        });
    }
};
