<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'phone',
        'address',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public static function getDefaultBranch(): ?self
    {
        return self::query()->where('is_default', true)->first()
            ?? self::query()->orderBy('id')->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(BranchProductStock::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
