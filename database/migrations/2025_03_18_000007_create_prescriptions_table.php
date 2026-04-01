<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('left_eye_sphere', 6, 2)->nullable();
            $table->decimal('left_eye_cylinder', 6, 2)->nullable();
            $table->integer('left_eye_axis')->nullable();
            $table->decimal('right_eye_sphere', 6, 2)->nullable();
            $table->decimal('right_eye_cylinder', 6, 2)->nullable();
            $table->integer('right_eye_axis')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
