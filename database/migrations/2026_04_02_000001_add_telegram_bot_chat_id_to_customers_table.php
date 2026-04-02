<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('telegram_bot_chat_id')
                ->nullable()
                ->after('telegram_peer_id')
                ->constrained('telegram_bot_chats')
                ->nullOnDelete();

            $table->unique('telegram_bot_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['telegram_bot_chat_id']);
        });
    }
};
