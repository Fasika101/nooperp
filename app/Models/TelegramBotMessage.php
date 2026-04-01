<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramBotMessage extends Model
{
    public const DIRECTION_INCOMING = 'incoming';

    public const DIRECTION_OUTGOING = 'outgoing';

    protected $fillable = [
        'telegram_bot_chat_id',
        'telegram_message_id',
        'direction',
        'sent_at',
        'text',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'telegram_message_id' => 'integer',
            'sent_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramBotChat::class, 'telegram_bot_chat_id');
    }
}
