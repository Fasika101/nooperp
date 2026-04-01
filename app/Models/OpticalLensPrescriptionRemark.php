<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpticalLensPrescriptionRemark extends Model
{
    protected $fillable = [
        'name',
        'price_single_vision',
        'price_progressive',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_single_vision' => 'decimal:2',
            'price_progressive' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function priceForVision(string $vision): float
    {
        return (float) match ($vision) {
            'progressive' => $this->price_progressive,
            default => $this->price_single_vision,
        };
    }
}
