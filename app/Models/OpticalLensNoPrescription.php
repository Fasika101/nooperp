<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpticalLensNoPrescription extends Model
{
    protected $fillable = [
        'name',
        'price',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
