<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDeletionLog extends Model
{
    protected $fillable = [
        'original_order_id',
        'customer_name',
        'branch_name',
        'affiliate_name',
        'affiliate_commission_amount',
        'total_amount',
        'amount_paid',
        'payment_status',
        'order_status',
        'items_snapshot',
        'deleted_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'affiliate_commission_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'items_snapshot' => 'array',
        ];
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}
