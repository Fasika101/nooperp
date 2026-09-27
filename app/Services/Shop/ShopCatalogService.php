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
     * @return Collection<int, array<string, mixed>>
     */
    public function framesForGender(int $genderOptionId): Collection
    {
        $branchId = $this->branchId();

        $products = Product::query()
            ->where('is_service', false)
            ->where('gender_option_id', $genderOptionId)
            ->with([
                'variants.colorOption:id,name',
                'variants.sizeOption:id,name',
                'variants.branchStocks' => fn ($q) => $q->where('branch_id', $branchId),
                'brand:id,name',
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
        $branchId = $this->branchId();

        $product = Product::query()
            ->where('is_service', false)
            ->with([
                'gender:id,name',
                'variants.colorOption:id,name',
                'variants.sizeOption:id,name',
                'variants.branchStocks' => fn ($q) => $q->where('branch_id', $branchId),
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
        ];
    }

    /**
     * Resolve a concrete in-stock variant for color (+ optional size).
     *
     * @return array{variant:ProductVariant,quantity:int}|null
     */
    public function resolveInStockVariant(int $productId, ?int $colorOptionId, ?int $sizeOptionId): ?array
    {
        $branchId = $this->branchId();

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
            $qty = (int) BranchProductStock::query()
                ->where('branch_id', $branchId)
                ->where('product_variant_id', $variant->id)
                ->value('quantity');
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
            $qty = (int) BranchProductStock::query()
                ->where('branch_id', $branchId)
                ->where('product_variant_id', $variant->id)
                ->value('quantity');
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

    protected function imageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        return Storage::disk('public')->url($image);
    }
}
