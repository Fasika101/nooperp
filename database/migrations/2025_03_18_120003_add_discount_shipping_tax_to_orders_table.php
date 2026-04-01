<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('total_amount');
            $table->string('discount_type')->default('fixed')->after('discount_amount'); // 'fixed' or 'percentage'
            $table->decimal('shipping_amount', 12, 2)->default(0)->after('discount_type');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('shipping_amount');
            $table->foreignId('tax_type_id')->nullable()->after('tax_amount')->constrained('tax_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['tax_type_id']);
            $table->dropColumn(['discount_amount', 'discount_type', 'shipping_amount', 'tax_amount', 'tax_type_id']);
        });
    }
};
