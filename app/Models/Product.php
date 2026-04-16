<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection as SupportCollection;

class Product extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'size_option_id',
        'color_option_id',
        'gender_option_id',
        'material_option_id',
        'shape_option_id',
        'brand_option_id',
        'lens_width_mm',
        'bridge_width_mm',
        'temple_length_mm',
        'price',
        'cost_price',
        'stock',
        'image',
        'is_service',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'lens_width_mm' => 'decimal:1',
            'bridge_width_mm' => 'decimal:1',
            'temple_length_mm' => 'decimal:1',
            'is_service' => 'boolean',
        ];
    }

    public static function opticalServiceProductId(): ?int
    {
        return static::query()->where('is_service', true)->value('id');
    }

    /**
     * @param  array<int|string|null>  $ids
     * @return list<int>
     */
    public static function validatedOptionIdsForType(array $ids, string $type): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($v) => (int) $v, $ids), static fn (int $v) => $v > 0)));
        if ($ids === []) {
            return [];
        }

        return ProductOption::query()
            ->whereIn('id', $ids)
            ->where('type', $type)
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockPurchases(): HasMany
    {
        return $this->hasMany(StockPurchase::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasManyThrough<BranchProductStock, ProductVariant, $this>
     */
    public function branchStocks(): HasManyThrough
    {
        return $this->hasManyThrough(
            BranchProductStock::class,
            ProductVariant::class,
            'product_id',
            'product_variant_id',
            'id',
            'id',
        );
    }

    /**
     * Sizes, colors, etc. offered for this product at POS (many allowed per product).
     *
     * @return BelongsToMany<ProductOption, $this>
     */
    public function attachedProductOptions(): BelongsToMany
    {
        return $this->belongsToMany(ProductOption::class, 'product_product_option')->withTimestamps();
    }

    /**
     * @return Collection<int, ProductOption>
     */
    public function availableSizeOptions(): Collection
    {
        $fromPivot = $this->attachedProductOptions()
            ->where('product_options.type', ProductOption::TYPE_SIZE)
            ->orderBy('product_options.name')
            ->get();

        if ($fromPivot->isNotEmpty()) {
            return $fromPivot;
        }

        if ($this->size_option_id) {
            $one = ProductOption::query()
                ->whereKey($this->size_option_id)
                ->where('type', ProductOption::TYPE_SIZE)
                ->first();

            return $one ? new Collection([$one]) : new Collection;
        }

        return new Collection;
    }

    /**
     * @return Collection<int, ProductOption>
     */
    public function availableColorOptions(): Collection
    {
        $fromPivot = $this->attachedProductOptions()
            ->where('product_options.type', ProductOption::TYPE_COLOR)
            ->orderBy('product_options.name')
            ->get();

        if ($fromPivot->isNotEmpty()) {
            return $fromPivot;
        }

        if ($this->color_option_id) {
            $one = ProductOption::query()
                ->whereKey($this->color_option_id)
                ->where('type', ProductOption::TYPE_COLOR)
                ->first();

            return $one ? new Collection([$one]) : new Collection;
        }

        return new Collection;
    }

    public function posNeedsVariantModal(): bool
    {
        return $this->availableColorOptions()->count() > 1
            || $this->availableSizeOptions()->count() > 1;
    }

    /**
     * Label for cart/receipt when a size + color are chosen.
     */
    public function formatNameWithVariant(?int $sizeOptionId, ?int $colorOptionId): string
    {
        $parts = [];
        if ($sizeOptionId) {
            $parts[] = ProductOption::query()->whereKey($sizeOptionId)->value('name');
        }
        if ($colorOptionId) {
            $parts[] = ProductOption::query()->whereKey($colorOptionId)->value('name');
        }
        $extra = array_filter($parts);
        if ($extra === []) {
            return $this->name;
        }

        return $this->name.' — '.implode(', ', $extra);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'size_option_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'color_option_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'gender_option_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'material_option_id');
    }

    public function shape(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'shape_option_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'brand_option_id');
    }

    public function getStockForBranch(?int $branchId): int
    {
        if (! $branchId) {
            return 0;
        }

        if ($this->relationLoaded('branchStocks')) {
            return (int) $this->branchStocks
                ->where('branch_id', $branchId)
                ->sum('quantity');
        }

        return (int) $this->branchStocks()
            ->where('branch_id', $branchId)
            ->sum('quantity');
    }

    /**
     * Color names for POS display (prefers eager-loaded attachedProductOptions).
     *
     * @return SupportCollection<int, string>
     */
    public function posColorLabels(): SupportCollection
    {
        if ($this->relationLoaded('attachedProductOptions')) {
            $names = $this->attachedProductOptions
                ->where('type', ProductOption::TYPE_COLOR)
                ->sortBy('name')
                ->pluck('name')
                ->filter()
                ->unique()
                ->values();
            if ($names->isNotEmpty()) {
                return SupportCollection::make($names->all());
            }
        }

        if ($this->relationLoaded('color') && $this->color?->name) {
            return SupportCollection::make([$this->color->name]);
        }

        return SupportCollection::make(
            $this->availableColorOptions()->pluck('name')->unique()->values()->all()
        );
    }

    /**
     * Frame / lens size option names for POS display.
     *
     * @return SupportCollection<int, string>
     */
    public function posSizeLabels(): SupportCollection
    {
        if ($this->relationLoaded('attachedProductOptions')) {
            $names = $this->attachedProductOptions
                ->where('type', ProductOption::TYPE_SIZE)
                ->sortBy('name')
                ->pluck('name')
                ->filter()
                ->unique()
                ->values();
            if ($names->isNotEmpty()) {
                return SupportCollection::make($names->all());
            }
        }

        if ($this->relationLoaded('size') && $this->size?->name) {
            return SupportCollection::make([$this->size->name]);
        }

        return SupportCollection::make(
            $this->availableSizeOptions()->pluck('name')->unique()->values()->all()
        );
    }

    /**
     * Lens width / bridge / temple for POS (searchable and display).
     */
    public function posMeasurementsLabel(): ?string
    {
        $has = $this->lens_width_mm !== null
            || $this->bridge_width_mm !== null
            || $this->temple_length_mm !== null;
        if (! $has) {
            return null;
        }

        $fmt = static fn ($v): string => number_format((float) $v, 1, '.', '');

        $parts = [];
        if ($this->lens_width_mm !== null) {
            $parts[] = 'Lens '.$fmt($this->lens_width_mm).'mm';
        }
        if ($this->bridge_width_mm !== null) {
            $parts[] = 'Bridge '.$fmt($this->bridge_width_mm).'mm';
        }
        if ($this->temple_length_mm !== null) {
            $parts[] = 'Temple '.$fmt($this->temple_length_mm).'mm';
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
