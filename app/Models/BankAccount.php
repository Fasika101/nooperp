<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'name',
        'branch_id',
        'bank_name',
        'account_number',
        'currency',
        'opening_balance',
        'current_balance',
        'is_default',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public static function getTotalCapital(): float
    {
        return (float) self::sum('opening_balance');
    }

    public static function getTotalBankBalance(): float
    {
        return (float) self::sum('current_balance');
    }

    public static function getDefaultAccount(): ?self
    {
        return self::where('is_default', true)->first()
            ?? self::orderBy('id')->first();
    }

    public static function getDefaultAccountForBranch(?int $branchId): ?self
    {
        if ($branchId) {
            return self::query()
                ->where('branch_id', $branchId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();
        }

        return self::getDefaultAccount();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class);
    }
}
