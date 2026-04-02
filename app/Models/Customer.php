<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'telegram_peer_id',
        'telegram_bot_chat_id',
        'name',
        'phone',
        'email',
        'address',
        'tin',
    ];

    public function telegramBotChat(): BelongsTo
    {
        return $this->belongsTo(TelegramBotChat::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }
}
