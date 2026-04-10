<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->decimal('from_amount', 14, 2)->default(0);
            $table->decimal('to_amount', 14, 2)->nullable()->comment('Null = no upper cap');
            $table->decimal('rate_percent', 7, 4)->comment('Marginal rate for income in this band');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_tax_brackets');
    }
};
