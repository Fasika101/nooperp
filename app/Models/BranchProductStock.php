<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchProductStock extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'quantity',
        'avg_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'avg_cost' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
