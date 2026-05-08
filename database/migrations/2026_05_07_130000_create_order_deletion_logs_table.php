<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_order_id');
            $table->string('customer_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('affiliate_name')->nullable();
            $table->decimal('affiliate_commission_amount', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_status')->nullable();
            $table->string('order_status')->nullable();
            $table->json('items_snapshot')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_deletion_logs');
    }
};
