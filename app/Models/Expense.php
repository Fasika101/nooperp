<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'date',
        'amount',
        'expense_type_id',
        'bank_account_id',
        'branch_id',
        'employee_id',
        'affiliate_id',
        'vendor',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Expense $expense): void {
            $payoutId = ExpenseType::affiliatePayoutTypeId();
            if (! $payoutId || (int) $expense->expense_type_id !== (int) $payoutId) {
                $expense->affiliate_id = null;
            }
        });
    }
}
