<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->decimal('product_cost_price', 12, 2)->nullable();
            $table->unsignedInteger('remaining_stock')->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_deletion_logs');
    }
};
