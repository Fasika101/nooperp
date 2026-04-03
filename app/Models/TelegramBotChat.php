<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'last_incoming_message_at',
        'staff_last_read_at',
        'message_count',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'last_message_at' => 'datetime',
            'last_incoming_message_at' => 'datetime',
            'staff_last_read_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramBotMessage::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Chats with inbound messages that staff has not “opened” since (or never read).
     */
    public function scopeUnreadByStaff(Builder $query): void
    {
        $query
            ->whereNotNull('last_incoming_message_at')
            ->where(function (Builder $q): void {
                $q->whereNull('staff_last_read_at')
                    ->orWhereColumn('last_incoming_message_at', '>', 'staff_last_read_at');
            });
    }

    public function isUnreadByStaff(): bool
    {
        $lastIn = $this->last_incoming_message_at;
        if ($lastIn === null) {
            return false;
        }

        $read = $this->staff_last_read_at;
        if ($read === null) {
            return true;
        }

        return $lastIn->greaterThan($read);
    }

    public function getDisplayTitleAttribute(): string
    {
        if ($this->title) {
            return $this->title;
        }
        $crmName = $this->meta['customer_display_name'] ?? null;
        if (is_string($crmName) && trim($crmName) !== '') {
            return trim($crmName);
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
