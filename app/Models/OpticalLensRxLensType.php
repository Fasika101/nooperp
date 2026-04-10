<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpticalLensRxLensType extends Model
{
    protected $table = 'optical_lens_rx_lens_types';

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function orderItemRxExtras(): HasMany
    {
        return $this->hasMany(OrderItemRxExtra::class, 'optical_lens_rx_lens_type_id');
    }
}
