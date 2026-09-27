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
     * Find or create a customer for online / shop orders without renaming past orders.
     *
     * Reuses a record only when phone (or email) matches AND the name matches.
     * Never overwrites an existing customer's name — that would rewrite history on
     * every earlier order that points at the same customer_id.
     *
     * @param  array{name:string,phone?:?string,email?:?string,address?:?string}  $payload
     */
    public static function resolveForOnlineOrder(array $payload): self
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $phone = filled($payload['phone'] ?? null) ? trim((string) $payload['phone']) : null;
        $email = filled($payload['email'] ?? null) ? strtolower(trim((string) $payload['email'])) : null;
        $address = filled($payload['address'] ?? null) ? trim((string) $payload['address']) : null;

        $customer = null;

        if ($phone) {
            $byPhone = static::query()->where('phone', $phone)->orderBy('id')->get();
            $customer = $byPhone->first(
                fn (self $c) => strcasecmp(trim((string) $c->name), $name) === 0
            );

            if (! $customer) {
                $blankName = $byPhone->first(fn (self $c) => trim((string) $c->name) === '');
                if ($blankName) {
                    $blankName->fill(array_filter([
                        'name' => $name !== '' ? $name : null,
                        'email' => $email,
                        'address' => $address,
                    ], fn ($v) => $v !== null))->save();

                    return $blankName->fresh();
                }
            }
        }

        if (! $customer && $email) {
            $byEmail = static::query()->where('email', $email)->orderBy('id')->get();
            $customer = $byEmail->first(
                fn (self $c) => strcasecmp(trim((string) $c->name), $name) === 0
            );

            if (! $customer) {
                $blankName = $byEmail->first(fn (self $c) => trim((string) $c->name) === '');
                if ($blankName) {
                    $blankName->fill(array_filter([
                        'name' => $name !== '' ? $name : null,
                        'phone' => $phone,
                        'address' => $address,
                    ], fn ($v) => $v !== null))->save();

                    return $blankName->fresh();
                }
            }
        }

        if ($customer) {
            // Same person (matched name): refresh contact details only — never rename.
            $updates = [];
            if ($phone && blank($customer->phone)) {
                $updates['phone'] = $phone;
            }
            if ($email && blank($customer->email)) {
                $updates['email'] = $email;
            }
            if ($address !== null) {
                $updates['address'] = $address;
            }
            if ($updates !== []) {
                $customer->fill($updates)->save();
            }

            return $customer->fresh();
        }

        return static::create([
            'name' => $name !== '' ? $name : 'Customer',
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
        ]);
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
