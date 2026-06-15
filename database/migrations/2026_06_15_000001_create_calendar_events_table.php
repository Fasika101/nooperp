<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('color', 20)->default('violet');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('location', 255)->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('calendar_event_user')) {
            Schema::create('calendar_event_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['calendar_event_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_user');
        Schema::dropIfExists('calendar_events');
    }
};
