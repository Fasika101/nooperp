<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentType extends Model
{
    protected $fillable = [
        'name',
        'branch_id',
        'is_global',
        'bank_account_id',
        'is_active',
        'is_accounts_receivable',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_accounts_receivable' => 'boolean',
            'is_global' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (PaymentType $paymentType): void {
            if ($paymentType->is_global) {
                return;
            }
            if ($paymentType->branch_id && ! $paymentType->branches()->exists()) {
                $paymentType->branches()->attach($paymentType->branch_id);
            }
        });
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'payment_type_branch')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Payment types available at the given branch (or all if no branch).
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

    public function scopeForAllBranches(Builder $query, array $branchIds): Builder
    {
        $branchIds = array_values(array_unique(array_filter(array_map('intval', $branchIds))));
        foreach ($branchIds as $bid) {
            $query->forBranch($bid);
        }

        return $query;
    }

    /**
     * Payment types usable at ANY of the given branches (OR logic — for multi-branch user filtering).
     *
     * @param  list<int>  $branchIds
     */
    public function scopeForAnyBranch(Builder $query, array $branchIds): Builder
    {
        $branchIds = array_values(array_unique(array_filter(array_map('intval', $branchIds))));
        if (empty($branchIds)) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($branchIds): void {
            foreach ($branchIds as $bid) {
                $outer->orWhere(function (Builder $q) use ($bid): void {
                    $q->where('is_global', true)
                        ->orWhereHas('branches', fn (Builder $b) => $b->where('branches.id', $bid))
                        ->orWhere(function (Builder $q2) use ($bid): void {
                            $q2->where('is_global', false)
                                ->whereDoesntHave('branches')
                                ->where(function (Builder $q3) use ($bid): void {
                                    $q3->whereNull('branch_id')
                                        ->orWhere('branch_id', $bid);
                                });
                        });
                });
            }
        });
    }
}
