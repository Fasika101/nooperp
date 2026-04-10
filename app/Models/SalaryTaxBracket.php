<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalaryTaxBracket extends Model
{
    protected $fillable = [
        'from_amount',
        'to_amount',
        'rate_percent',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'from_amount' => 'decimal:2',
            'to_amount' => 'decimal:2',
            'rate_percent' => 'decimal:4',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('from_amount');
    }
}
