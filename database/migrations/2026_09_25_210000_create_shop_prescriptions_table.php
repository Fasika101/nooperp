<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('batch_token', 64)->index();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->json('scan_json')->nullable();
            $table->string('vision_type')->nullable();
            $table->string('confidence')->nullable();
            $table->decimal('prescription_price', 12, 2)->default(0);
            $table->text('scan_notes')->nullable();
            $table->boolean('scan_ok')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_prescriptions');
    }
};
