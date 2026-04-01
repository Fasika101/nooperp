<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramBotChat extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'type',
        'title',
        'username',
        'first_name',
        'last_name',
        'last_message_at',
        'message_count',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'last_message_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramBotMessage::class);
    }

    public function getDisplayTitleAttribute(): string
    {
        if ($this->title) {
            return $this->title;
        }
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        if ($name !== '') {
            return $name;
        }
        if ($this->username) {
            return '@'.$this->username;
        }

        return 'Chat '.$this->telegram_chat_id;
    }
}
