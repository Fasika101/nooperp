<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDeletionLog extends Model
{
    protected $fillable = [
        'product_name',
        'product_cost_price',
        'remaining_stock',
        'refunded_amount',
        'bank_account_id',
        'deleted_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'product_cost_price' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'remaining_stock' => 'integer',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}
