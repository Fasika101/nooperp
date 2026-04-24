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
     * Colors from pivot / legacy product columns (catalog config), not filtered by branch stock.
     * Optionally narrow to colors that appear on a variant with $onlyForSizeOptionId for this product.
     *
     * @return Collection<int, ProductOption>
     */
    public function configuredColorOptions(?int $onlyForSizeOptionId = null): Collection
    {
        if ($onlyForSizeOptionId !== null) {
            $narrowed = $this->attachedProductOptions()
                ->where('product_options.type', ProductOption::TYPE_COLOR)
                ->orderBy('product_options.name')
                ->whereHas('variants', function ($vq) use ($onlyForSizeOptionId) {
                    $vq->where('product_id', $this->id)
                        ->where('size_option_id', $onlyForSizeOptionId);
                })
                ->get();
            if ($narrowed->isNotEmpty()) {
                return $narrowed;
            }
        }

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

    /**
     * Sizes from pivot / legacy product columns (catalog config), not filtered by branch stock.
     * Optionally narrow to sizes that appear on a variant with $onlyForColorOptionId for this product.
     *
     * @return Collection<int, ProductOption>
     */
    public function configuredSizeOptions(?int $onlyForColorOptionId = null): Collection
    {
        if ($onlyForColorOptionId !== null) {
            $narrowed = $this->attachedProductOptions()
                ->where('product_options.type', ProductOption::TYPE_SIZE)
                ->orderBy('product_options.name')
                ->whereHas('variants', function ($vq) use ($onlyForColorOptionId) {
                    $vq->where('product_id', $this->id)
                        ->where('color_option_id', $onlyForColorOptionId);
                })
                ->get();
            if ($narrowed->isNotEmpty()) {
                return $narrowed;
            }
        }

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
     * POS dropdowns: prefer in-stock options for the branch; fall back to configured options so the UI is usable
     * when variant rows or stock links are incomplete.
     *
     * @return Collection<int, ProductOption>
     */
    public function posSelectableColorOptions(?int $branchId, ?int $onlyForSizeOptionId = null): Collection
    {
        $available = $this->availableColorOptions($branchId, $onlyForSizeOptionId);
        if ($available->isNotEmpty()) {
            return $available;
        }

        return $this->configuredColorOptions($onlyForSizeOptionId);
    }

    /**
     * @return Collection<int, ProductOption>
     */
    public function posSelectableSizeOptions(?int $branchId, ?int $onlyForColorOptionId = null): Collection
    {
        $available = $this->availableSizeOptions($branchId, $onlyForColorOptionId);
        if ($available->isNotEmpty()) {
            return $available;
        }

        return $this->configuredSizeOptions($onlyForColorOptionId);
    }

    /**
     * Whether this (color, size) pair exists on a {@see ProductVariant} row when the product has variants.
     */
    public function posVariantPairIsAllowed(?int $colorOptionId, ?int $sizeOptionId): bool
    {
        if (! $this->variants()->exists()) {
            return true;
        }

        $q = ProductVariant::query()->where('product_id', $this->id);
        if ($colorOptionId !== null) {
            $q->where('color_option_id', $colorOptionId);
        } else {
            $q->whereNull('color_option_id');
        }
        if ($sizeOptionId !== null) {
            $q->where('size_option_id', $sizeOptionId);
        } else {
            $q->whereNull('size_option_id');
        }

        return $q->exists();
    }

    /**
     * Sizes that can be sold for this product. Optionally restrict to variants that include $onlyForColorOptionId
     * and have branch stock quantity &gt;= 1 when $branchId is set (POS). Omit $branchId to list all attached sizes (e.g. stock purchase).
     *
     * @return Collection<int, ProductOption>
     */
    public function availableSizeOptions(?int $branchId = null, ?int $onlyForColorOptionId = null): Collection
    {
        $query = $this->attachedProductOptions()
            ->where('product_options.type', ProductOption::TYPE_SIZE)
            ->orderBy('product_options.name');

        if ($branchId !== null) {
            $query->whereHas('variants', function ($vq) use ($branchId, $onlyForColorOptionId) {
                $vq->where('product_id', $this->id);
                if ($onlyForColorOptionId !== null) {
                    $vq->where('color_option_id', $onlyForColorOptionId);
                }
                $vq->whereHas('branchStocks', function ($sq) use ($branchId) {
                    $sq->where('branch_id', $branchId)
                        ->where('quantity', '>=', 1);
                });
            });
        }

        $fromPivot = $query->get();

        if ($fromPivot->isNotEmpty()) {
            return $fromPivot;
        }

        if ($this->size_option_id) {
            $oneQuery = ProductOption::query()
                ->whereKey($this->size_option_id)
                ->where('type', ProductOption::TYPE_SIZE);

            if ($branchId !== null) {
                $oneQuery->whereHas('variants', function ($vq) use ($branchId, $onlyForColorOptionId) {
                    $vq->where('product_id', $this->id);
                    if ($onlyForColorOptionId !== null) {
                        $vq->where('color_option_id', $onlyForColorOptionId);
                    }
                    $vq->whereHas('branchStocks', function ($sq) use ($branchId) {
                        $sq->where('branch_id', $branchId)
                            ->where('quantity', '>=', 1);
                    });
                });
            }

            $one = $oneQuery->first();

            if ($one) {
                return new Collection([$one]);
            }
        }

        return new Collection;
    }

    /**
     * Colors that can be sold for this product. Optionally restrict to variants that include $onlyForSizeOptionId
     * and have branch stock quantity &gt;= 1 when $branchId is set (POS). Omit $branchId to list all attached colors (e.g. stock purchase).
     *
     * @return Collection<int, ProductOption>
     */
    public function availableColorOptions(?int $branchId = null, ?int $onlyForSizeOptionId = null): Collection
    {
        $query = $this->attachedProductOptions()
            ->where('product_options.type', ProductOption::TYPE_COLOR)
            ->orderBy('product_options.name');

        if ($branchId !== null) {
            $query->whereHas('variants', function ($vq) use ($branchId, $onlyForSizeOptionId) {
                $vq->where('product_id', $this->id);
                if ($onlyForSizeOptionId !== null) {
                    $vq->where('size_option_id', $onlyForSizeOptionId);
                }
                $vq->whereHas('branchStocks', function ($sq) use ($branchId) {
                    $sq->where('branch_id', $branchId)
                        ->where('quantity', '>=', 1);
                });
            });
        }

        $fromPivot = $query->get();

        if ($fromPivot->isNotEmpty()) {
            return $fromPivot;
        }

        if ($this->color_option_id) {
            $oneQuery = ProductOption::query()
                ->whereKey($this->color_option_id)
                ->where('type', ProductOption::TYPE_COLOR);

            if ($branchId !== null) {
                $oneQuery->whereHas('variants', function ($vq) use ($branchId, $onlyForSizeOptionId) {
                    $vq->where('product_id', $this->id);
                    if ($onlyForSizeOptionId !== null) {
                        $vq->where('size_option_id', $onlyForSizeOptionId);
                    }
                    $vq->whereHas('branchStocks', function ($sq) use ($branchId) {
                        $sq->where('branch_id', $branchId)
                            ->where('quantity', '>=', 1);
                    });
                });
            }

            $one = $oneQuery->first();

            if ($one) {
                return new Collection([$one]);
            }
        }

        return new Collection;
    }

    /**
     * Open the POS variant modal when the catalog defines choices (pivot / legacy), not only when
     * multiple SKUs are in stock — so staff pick color/size on add, same as lens customization.
     */
    public function posNeedsVariantModal(?int $branchId = null): bool
    {
        $colors = $this->configuredColorOptions();
        $sizes = $this->configuredSizeOptions();

        if ($colors->count() > 1 || $sizes->count() > 1) {
            return true;
        }

        return $colors->isNotEmpty() && $sizes->isNotEmpty();
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
    public function posColorLabels(?int $branchId = null): SupportCollection
    {
        return SupportCollection::make(
            $this->configuredColorOptions()->pluck('name')->unique()->values()->all()
        );
    }

    /**
     * Frame / lens size option names for POS display.
     *
     * @return SupportCollection<int, string>
     */
    public function posSizeLabels(?int $branchId = null): SupportCollection
    {
        return SupportCollection::make(
            $this->configuredSizeOptions()->pluck('name')->unique()->values()->all()
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
