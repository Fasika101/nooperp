<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    public const COMMISSION_ADD_PERCENT = 'add_percent';

    public const COMMISSION_DEDUCT_PERCENT = 'deduct_percent';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'code',
        'default_commission_type',
        'default_commission_rate',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'default_commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Expense rows for commission payouts (matches {@see ExpenseType::NAME_AFFILIATE_PAYOUT}).
     */
    public function commissionPayouts(): HasMany
    {
        $typeId = ExpenseType::affiliatePayoutTypeId();

        return $this->hasMany(Expense::class, 'affiliate_id')
            ->when(
                $typeId,
                fn ($q) => $q->where('expense_type_id', $typeId),
                fn ($q) => $q->whereRaw('0 = 1'),
            );
    }

    public function getDisplayLabelAttribute(): string
    {
        $phone = $this->phone ? " · {$this->phone}" : '';

        return $this->name.$phone;
    }
}
