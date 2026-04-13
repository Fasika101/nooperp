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
        'credit_limit',
        'credit_blocked',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'credit_blocked' => 'boolean',
        ];
    }

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

    public function isWalkIn(): bool
    {
        return $this->email === 'walkin@pos.local';
    }

    /**
     * Sum of open balances on completed orders (A/R).
     */
    public function outstandingBalance(): float
    {
        return (float) Order::query()
            ->where('customer_id', $this->id)
            ->where('status', 'completed')
            ->where('balance_due', '>', 0)
            ->sum('balance_due');
    }

    /**
     * Whether a new charge of $amount is within credit_limit (null limit = unlimited).
     */
    public function canChargeAmount(float $amount): bool
    {
        if ($this->credit_blocked) {
            return false;
        }

        if ($this->credit_limit === null) {
            return true;
        }

        return $this->outstandingBalance() + $amount <= (float) $this->credit_limit + 0.0001;
    }
}
