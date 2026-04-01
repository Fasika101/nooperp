<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telegram_bot_chats')) {
            Schema::create('telegram_bot_chats', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('telegram_chat_id')->unique();
                $table->string('type', 32)->default('private');
                $table->string('title')->nullable();
                $table->string('username')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->unsignedInteger('message_count')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('telegram_bot_messages')) {
            Schema::create('telegram_bot_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('telegram_bot_chat_id')->constrained('telegram_bot_chats')->cascadeOnDelete();
                $table->bigInteger('telegram_message_id');
                $table->string('direction', 16);
                $table->timestamp('sent_at')->nullable();
                $table->text('text')->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();

                $table->unique(['telegram_bot_chat_id', 'telegram_message_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_messages');
        Schema::dropIfExists('telegram_bot_chats');
    }
};
