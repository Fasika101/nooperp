<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'color_option_id',
        'size_option_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function colorOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'color_option_id');
    }

    public function sizeOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'size_option_id');
    }

    public function branchStocks(): HasMany
    {
        return $this->hasMany(BranchProductStock::class, 'product_variant_id');
    }

    /**
     * One row per (product, color, size) combination used for inventory.
     */
    public static function findOrCreateForProduct(int $productId, ?int $colorOptionId, ?int $sizeOptionId): self
    {
        $query = static::query()->where('product_id', $productId);
        if ($colorOptionId !== null) {
            $query->where('color_option_id', $colorOptionId);
        } else {
            $query->whereNull('color_option_id');
        }
        if ($sizeOptionId !== null) {
            $query->where('size_option_id', $sizeOptionId);
        } else {
            $query->whereNull('size_option_id');
        }

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        return static::query()->create([
            'product_id' => $productId,
            'color_option_id' => $colorOptionId,
            'size_option_id' => $sizeOptionId,
        ]);
    }

    public function label(): string
    {
        $parts = array_filter([
            $this->colorOption?->name,
            $this->sizeOption?->name,
        ]);

        return $parts === [] ? 'Default' : implode(' / ', $parts);
    }
}
