<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vision')->nullable(); // single, progressive
            $table->decimal('left_eye_add', 6, 2)->nullable();
            $table->decimal('right_eye_add', 6, 2)->nullable();
            $table->string('pd_mode')->nullable(); // one, two
            $table->decimal('pd_single', 6, 2)->nullable();
            $table->decimal('pd_right', 6, 2)->nullable();
            $table->decimal('pd_left', 6, 2)->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescription_id');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropColumn([
                'vision',
                'left_eye_add',
                'right_eye_add',
                'pd_mode',
                'pd_single',
                'pd_right',
                'pd_left',
            ]);
        });
    }
};
