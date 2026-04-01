<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramChat extends Model
{
    protected $fillable = [
        'telegram_peer_id',
        'type',
        'title',
        'username',
        'last_message_at',
        'message_count',
        'imported_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'imported_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->title
            ?? ($this->username ? '@'.$this->username : null)
            ?? 'Chat '.$this->telegram_peer_id;
    }
}
