<?php

use App\Support\Migration\DropsForeignKeysSafely;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use DropsForeignKeysSafely;

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
            $table->dropUnique(['telegram_bot_chat_id']);
        });
        $this->dropForeignKeyIfExists('customers', 'telegram_bot_chat_id');
        if (Schema::hasColumn('customers', 'telegram_bot_chat_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('telegram_bot_chat_id');
            });
        }
    }
};
