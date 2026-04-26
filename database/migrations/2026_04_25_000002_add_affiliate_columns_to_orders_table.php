<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('affiliate_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('affiliates')
                ->nullOnDelete();
            $table->string('affiliate_commission_type', 20)->nullable()->after('affiliate_id');
            $table->decimal('affiliate_commission_rate', 5, 2)->nullable()->after('affiliate_commission_type');
            $table->decimal('affiliate_commission_amount', 12, 2)->default(0)->after('affiliate_commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'affiliate_commission_type',
                'affiliate_commission_rate',
                'affiliate_commission_amount',
            ]);
        });
    }
};
