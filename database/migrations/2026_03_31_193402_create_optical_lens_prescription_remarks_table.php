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
        Schema::create('optical_lens_prescription_remarks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price_single_vision', 12, 2);
            $table->decimal('price_progressive', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optical_lens_prescription_remarks');
    }
};
