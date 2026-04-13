<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = [
        'name',
        'branch_id',
        'is_global',
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
            'is_global' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (BankAccount $account): void {
            if ($account->is_global) {
                return;
            }
            if ($account->branch_id && ! $account->branches()->exists()) {
                $account->branches()->attach($account->branch_id);
            }
        });
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
                ->forBranch($branchId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();
        }

        return self::getDefaultAccount();
    }

    /**
     * Accounts that can be used for operations at the given branch (or everywhere if no branch).
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        if (! $branchId) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($branchId): void {
            $q->where('is_global', true)
                ->orWhereHas('branches', fn (Builder $b) => $b->where('branches.id', $branchId))
                ->orWhere(function (Builder $q2) use ($branchId): void {
                    $q2->where('is_global', false)
                        ->whereDoesntHave('branches')
                        ->where(function (Builder $q3) use ($branchId): void {
                            $q3->whereNull('branch_id')
                                ->orWhere('branch_id', $branchId);
                        });
                });
        });
    }

    /**
     * Accounts usable at every branch in the list (e.g. multi-branch restock paid from one account).
     *
     * @param  list<int>  $branchIds
     */
    public function scopeForAllBranches(Builder $query, array $branchIds): Builder
    {
        $branchIds = array_values(array_unique(array_filter(array_map('intval', $branchIds))));
        foreach ($branchIds as $bid) {
            $query->forBranch($bid);
        }

        return $query;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'bank_account_branch')->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function isUsableAtBranch(?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }
        if ($this->is_global) {
            return true;
        }
        if ($this->relationLoaded('branches')) {
            return $this->branches->contains('id', (int) $branchId);
        }
        if ($this->branches()->exists()) {
            return $this->branches()->where('branches.id', $branchId)->exists();
        }
        if ($this->branch_id === null) {
            return true;
        }

        return (int) $this->branch_id === (int) $branchId;
    }

    /**
     * @param  list<int>  $branchIds
     */
    public function isUsableAtAllBranches(array $branchIds): bool
    {
        foreach (array_unique(array_filter(array_map('intval', $branchIds))) as $bid) {
            if (! $this->isUsableAtBranch($bid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * When a transaction has no branch, infer one only if the account maps to exactly one branch.
     */
    public function getSingleBranchIdForFallback(): ?int
    {
        if ($this->is_global) {
            return null;
        }
        if ($this->branches()->exists()) {
            return $this->branches()->count() === 1 ? (int) $this->branches()->first()->id : null;
        }

        return $this->branch_id !== null ? (int) $this->branch_id : null;
    }
}
