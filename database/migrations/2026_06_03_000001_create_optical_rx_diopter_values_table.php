<?php

use App\Support\OpticalRxConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('optical_rx_diopter_values')) {
            OpticalRxConfig::seedDefaults();

            return;
        }

        Schema::create('optical_rx_diopter_values', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32);
            $table->string('value', 16);
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['group', 'value']);
            $table->index(['group', 'sort_order']);
        });

        OpticalRxConfig::seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('optical_rx_diopter_values');
    }
};
