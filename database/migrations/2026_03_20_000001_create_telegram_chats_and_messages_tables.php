<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_peer_id')->unique();
            $table->string('type', 32)->default('user');
            $table->string('title')->nullable();
            $table->string('username')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_chat_id')->constrained('telegram_chats')->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_message_id');
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_outgoing')->default(false);
            $table->string('sender_peer_id')->nullable()->index();
            $table->string('sender_name')->nullable();
            $table->text('text')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['telegram_chat_id', 'telegram_message_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('telegram_messages', function (Blueprint $table) {
                $table->fullText(['text']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_chats');
    }
};
