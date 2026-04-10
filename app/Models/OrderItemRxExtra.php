<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemRxExtra extends Model
{
    protected $table = 'order_item_rx_extras';

    protected $fillable = [
        'order_item_id',
        'optical_lens_rx_lens_type_id',
        'name',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function lensType(): BelongsTo
    {
        return $this->belongsTo(OpticalLensRxLensType::class, 'optical_lens_rx_lens_type_id');
    }
}
