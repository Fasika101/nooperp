<?php

use App\Models\TelegramBotChat;
use App\Models\TelegramBotMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bot_chats', function (Blueprint $table) {
            $table->timestamp('last_incoming_message_at')->nullable()->after('last_message_at');
            $table->timestamp('staff_last_read_at')->nullable()->after('last_incoming_message_at');
        });

        TelegramBotChat::query()->select(['id'])->orderBy('id')->each(function (TelegramBotChat $chat): void {
            $max = TelegramBotMessage::query()
                ->where('telegram_bot_chat_id', $chat->id)
                ->where('direction', TelegramBotMessage::DIRECTION_INCOMING)
                ->max('sent_at');
            if ($max !== null) {
                $chat->update(['last_incoming_message_at' => $max]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bot_chats', function (Blueprint $table) {
            $table->dropColumn(['last_incoming_message_at', 'staff_last_read_at']);
        });
    }
};
