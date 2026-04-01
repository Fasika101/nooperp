<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'telegram_message_id',
        'sent_at',
        'is_outgoing',
        'sender_peer_id',
        'sender_name',
        'text',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'is_outgoing' => 'boolean',
            'raw' => 'array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'telegram_chat_id');
    }
}
