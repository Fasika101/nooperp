<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_import_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('row_name')->nullable();
            $table->string('row_phone')->nullable();
            $table->string('row_email')->nullable();
            $table->string('previous_name')->nullable();
            $table->string('current_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_import_audits');
    }
};
