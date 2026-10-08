<?php

namespace App\Services\Shop;

use App\Models\BranchProductStock;
use App\Models\OpticalLensNoPrescription;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ShopCatalogService
{
    /**
     * Preferred fulfillment branch (orders / payments still anchored here).
     * Catalog stock is summed across ALL branches.
     */
    public function branchId(): int
    {
        return (int) config('storefront.branch_id');
    }

    /**
     * @return Collection<int, ProductOption>
     */
    public function genders(): Collection
    {
        return ProductOption::query()
            ->where('type', ProductOption::TYPE_GENDER)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function gender(int $id): ?ProductOption
    {
        return ProductOption::query()
            ->where('type', ProductOption::TYPE_GENDER)
            ->whereKey($id)
            ->first();
    }

    /**
     * Blue cut + Anti glare from ERP "Lenses (no Rx)" table (one each).
     *
     * @return list<array{key:string,id:int,label:string,price:float}>
     */
    public function lensCoatings(): array
    {
        $lenses = OpticalLensNoPrescription::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $blueCutId = config('storefront.lens_blue_cut_id');
        $antiGlareId = config('storefront.lens_anti_glare_id');

        $blue = $blueCutId
            ? $lenses->firstWhere('id', (int) $blueCutId)
            : $lenses->first(fn (OpticalLensNoPrescription $l) => stripos($l->name, 'BLUE') !== false);

        $anti = $antiGlareId
            ? $lenses->firstWhere('id', (int) $antiGlareId)
            : $lenses->first(function (OpticalLensNoPrescription $l) {
                $n = strtoupper($l->name);

                return (str_contains($n, 'ANTI') || str_contains($n, 'ANTIGLARE'))
                    && ! str_contains($n, 'BLUE');
            });

        $out = [];
        if ($anti) {
            $out[] = [
                'key' => 'anti_glare',
                'id' => (int) $anti->id,
                'label' => 'Anti glare',
                'price' => (float) $anti->price,
            ];
        }
        if ($blue) {
            $out[] = [
                'key' => 'blue_cut',
                'id' => (int) $blue->id,
                'label' => 'Blue cut',
                'price' => (float) $blue->price,
            ];
        }

        return $out;
    }

    /**
     * Frames in stock for a gender — stock is total across ALL branches.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function framesForGender(int $genderOptionId): Collection
    {
        $products = Product::query()
            ->where('is_service', false)
            ->where('gender_option_id', $genderOptionId)
            ->with([
                'variants.colorOption:id,name',
                'variants.sizeOption:id,name',
                'variants.branchStocks.branch:id,name,address,google_maps_url,is_active',
                'brand:id,name',
                'gender:id,name',
            ])
            ->orderBy('name')
            ->get();

        return $products
            ->map(fn (Product $product) => $this->serializeFrame($product))
            ->filter(fn (array $row) => $row['stock'] > 0)
            ->values();
    }

    /**
     * Search in-stock frames by name or brand (all genders).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function searchFrames(string $query): Collection
    {
        $q = trim($query);
        if ($q === '') {
            return collect();
        }

        $like = '%'.$q.'%';

        $products = Product::query()
            ->where('is_service', false)
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like));
            })
            ->with([
                'variants.colorOption:id,name',
                'variants.sizeOption:id,name',
                'variants.branchStocks.branch:id,name,address,google_maps_url,is_active',
                'brand:id,name',
                'gender:id,name',
            ])
            ->orderBy('name')
            ->get();

        return $products
            ->map(fn (Product $product) => $this->serializeFrame($product))
            ->filter(fn (array $row) => $row['stock'] > 0)
            ->values();
    }

    public function frame(int $productId): ?array
    {
        $product = Product::query()
            ->where('is_service', false)
            ->with([
                'gender:id,name',
                'variants.colorOption:id,name',
                'variants.sizeOption:id,name',
                'variants.branchStocks.branch:id,name,address,google_maps_url,is_active',
                'brand:id,name',
            ])
            ->find($productId);

        return $product ? $this->serializeFrame($product) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeFrame(Product $product): array
    {
        $variants = $product->variants->map(function ($variant) {
            $qty = (int) $variant->branchStocks->sum('quantity');

            return [
                'variant_id' => $variant->id,
                'color_option_id' => $variant->color_option_id,
                'color_name' => $variant->colorOption?->name,
                'size_option_id' => $variant->size_option_id,
                'size_name' => $variant->sizeOption?->name,
                'quantity' => $qty,
            ];
        })->values();

        $inStock = $variants->filter(fn ($v) => (int) $v['quantity'] > 0)->values();
        $stock = (int) $inStock->sum('quantity');

        $colors = [];
        foreach ($inStock as $v) {
            $cid = $v['color_option_id'] !== null ? (int) $v['color_option_id'] : 0;
            $key = $cid > 0 ? (string) $cid : 'none';
            if (! isset($colors[$key])) {
                $colors[$key] = [
                    'id' => $cid > 0 ? $cid : null,
                    'name' => $v['color_name'] ?? 'Standard',
                    'quantity' => 0,
                    'sizes' => [],
                ];
            }
            $colors[$key]['quantity'] += (int) $v['quantity'];
            $sid = $v['size_option_id'] !== null ? (int) $v['size_option_id'] : null;
            $sizeKey = $sid !== null ? (string) $sid : 'none';
            if (! isset($colors[$key]['sizes'][$sizeKey])) {
                $colors[$key]['sizes'][$sizeKey] = [
                    'id' => $sid,
                    'name' => $v['size_name'] ?? 'One size',
                    'quantity' => 0,
                    'variant_id' => $v['variant_id'],
                ];
            }
            $colors[$key]['sizes'][$sizeKey]['quantity'] += (int) $v['quantity'];
        }

        $colorList = array_values(array_map(function (array $c) {
            $c['sizes'] = array_values($c['sizes']);

            return $c;
        }, $colors));

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'image' => $this->imageUrl($product->image),
            'brand' => $product->brand?->name,
            'gender_option_id' => $product->gender_option_id,
            'gender_name' => $product->gender?->name,
            'stock' => $stock,
            'colors' => $colorList,
            'variants' => $inStock->all(),
            // Banuba TINT SKU — must match the SKU registered in Banuba's catalog
            'try_on_sku' => (string) $product->id,
            'location' => $this->locationForProduct($product),
        ];
    }

    /**
     * Branch with stock + Google Maps share link for this product.
     * Prefers the storefront branch, then the branch with the most stock.
     *
     * @return array{branch_id:int,name:string,address:?string,maps_url:string}|null
     */
    protected function locationForProduct(Product $product): ?array
    {
        $preferred = $this->branchId();
        $byBranch = [];

        foreach ($product->variants as $variant) {
            foreach ($variant->branchStocks as $stock) {
                if ((int) $stock->quantity < 1) {
                    continue;
                }

                $branch = $stock->branch;
                if (! $branch || ! $branch->is_active || ! $branch->hasGoogleMapsUrl()) {
                    continue;
                }

                $id = (int) $branch->id;
                if (! isset($byBranch[$id])) {
                    $byBranch[$id] = [
                        'branch_id' => $id,
                        'name' => (string) $branch->name,
                        'address' => $branch->address,
                        'maps_url' => trim((string) $branch->google_maps_url),
                        'quantity' => 0,
                        'preferred' => $id === $preferred,
                    ];
                }
                $byBranch[$id]['quantity'] += (int) $stock->quantity;
            }
        }

        if ($byBranch === []) {
            return null;
        }

        usort($byBranch, function (array $a, array $b): int {
            if ($a['preferred'] !== $b['preferred']) {
                return $a['preferred'] ? -1 : 1;
            }

            return $b['quantity'] <=> $a['quantity'];
        });

        $best = $byBranch[0];
        unset($best['quantity'], $best['preferred']);

        return $best;
    }

    /**
     * Resolve a concrete in-stock variant for color (+ optional size).
     * Quantity is total across ALL branches.
     *
     * @return array{variant:ProductVariant,quantity:int}|null
     */
    public function resolveInStockVariant(int $productId, ?int $colorOptionId, ?int $sizeOptionId): ?array
    {
        $query = ProductVariant::query()
            ->where('product_id', $productId)
            ->when(
                $colorOptionId !== null && $colorOptionId > 0,
                fn ($q) => $q->where('color_option_id', $colorOptionId),
                fn ($q) => $q->whereNull('color_option_id'),
            );

        if ($sizeOptionId !== null && $sizeOptionId > 0) {
            $query->where('size_option_id', $sizeOptionId);
        }

        $variants = $query->get();
        foreach ($variants as $variant) {
            $qty = $this->totalStockForVariant((int) $variant->id);
            if ($qty > 0) {
                return ['variant' => $variant, 'quantity' => $qty];
            }
        }

        // If size was required but none matched with color, fail
        if ($sizeOptionId !== null && $sizeOptionId > 0) {
            return null;
        }

        // No size chosen: any in-stock variant for this color (or product)
        $fallback = ProductVariant::query()
            ->where('product_id', $productId)
            ->when(
                $colorOptionId !== null && $colorOptionId > 0,
                fn ($q) => $q->where('color_option_id', $colorOptionId),
            )
            ->get();

        foreach ($fallback as $variant) {
            $qty = $this->totalStockForVariant((int) $variant->id);
            if ($qty > 0) {
                return ['variant' => $variant, 'quantity' => $qty];
            }
        }

        return null;
    }

    public function availableStock(int $productId, ?int $colorOptionId = null, ?int $sizeOptionId = null): int
    {
        $resolved = $this->resolveInStockVariant($productId, $colorOptionId, $sizeOptionId);

        return $resolved['quantity'] ?? 0;
    }

    public function totalStockForVariant(int $variantId): int
    {
        return (int) BranchProductStock::query()
            ->where('product_variant_id', $variantId)
            ->sum('quantity');
    }

    /**
     * Decrement stock from a branch that has enough quantity.
     * Prefers STOREFRONT_BRANCH_ID, then the branch with the most stock.
     *
     * @return int|null Branch id stock was taken from, or null if insufficient
     */
    public function claimStock(int $variantId, int $qty): ?int
    {
        $qty = max(1, $qty);
        $preferred = $this->branchId();

        $stock = BranchProductStock::query()
            ->where('product_variant_id', $variantId)
            ->where('quantity', '>=', $qty)
            ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$preferred])
            ->orderByDesc('quantity')
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            return null;
        }

        $stock->decrement('quantity', $qty);

        return (int) $stock->branch_id;
    }

    protected function imageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        return Storage::disk('public')->url($image);
    }
}
