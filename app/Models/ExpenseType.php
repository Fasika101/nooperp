<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseType extends Model
{
    public const NAME_SALARIES = 'Salaries';

    public const NAME_AFFILIATE_PAYOUT = 'Affiliate commission payout';

    protected $fillable = [
        'name',
        'is_active',
        'is_recurring',
        'frequency',
        'day_of_month',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_recurring' => 'boolean',
        ];
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function salariesTypeId(): ?int
    {
        return once(fn (): ?int => static::query()->where('name', self::NAME_SALARIES)->value('id'));
    }

    public static function affiliatePayoutTypeId(): ?int
    {
        return once(fn (): ?int => static::query()->where('name', self::NAME_AFFILIATE_PAYOUT)->value('id'));
    }
}
