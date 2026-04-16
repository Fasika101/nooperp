<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductCreationService
{
    private static function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    public function create(array $data): Product
    {
        $initialStock = (int) ($data['stock'] ?? 0);
        $initialCost = (float) ($data['cost_price'] ?? 0);

        return DB::transaction(function () use ($data, $initialStock, $initialCost) {
            $sizeIds = Product::validatedOptionIdsForType((array) ($data['size_option_ids'] ?? []), ProductOption::TYPE_SIZE);
            $colorIds = Product::validatedOptionIdsForType((array) ($data['color_option_ids'] ?? []), ProductOption::TYPE_COLOR);
            $selectedSizeId = $sizeIds[0] ?? self::nullableOptionId($data['size_option_id'] ?? null);
            $selectedColorId = $colorIds[0] ?? self::nullableOptionId($data['color_option_id'] ?? null);

            $product = Product::query()->create([
                'image' => $this->normalizeProductImage($data['image'] ?? null),
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'size_option_id' => $selectedSizeId,
                'color_option_id' => $selectedColorId,
                'gender_option_id' => $data['gender_option_id'] ?? null,
                'material_option_id' => $data['material_option_id'] ?? null,
                'shape_option_id' => $data['shape_option_id'] ?? null,
                'brand_option_id' => $data['brand_option_id'] ?? null,
                'lens_width_mm' => self::nullableDecimal($data['lens_width_mm'] ?? null),
                'bridge_width_mm' => self::nullableDecimal($data['bridge_width_mm'] ?? null),
                'temple_length_mm' => self::nullableDecimal($data['temple_length_mm'] ?? null),
                'cost_price' => $initialStock > 0 ? 0 : ($data['cost_price'] ?? null),
                'price' => $data['price'],
                'stock' => 0,
            ]);

            $attachedOptionIds = array_values(array_unique(array_filter([
                ...$sizeIds,
                ...$colorIds,
                $selectedSizeId,
                $selectedColorId,
            ])));

            $product->attachedProductOptions()->sync($attachedOptionIds);

            if ($initialStock > 0 && $initialCost > 0) {
                $effectiveColorIds = $colorIds !== [] ? $colorIds : array_values(array_filter([$selectedColorId]));
                $effectiveSizeIds = $sizeIds !== [] ? $sizeIds : array_values(array_filter([$selectedSizeId]));
                $colorCount = count($effectiveColorIds);
                $sizeCount = count($effectiveSizeIds);
                $rawAllocations = (array) ($data['initial_stock_allocations'] ?? []);

                if ($colorCount === 1) {
                    foreach ($rawAllocations as &$row) {
                        $row['color_option_id'] = $effectiveColorIds[0];
                    }
                    unset($row);
                }

                if ($sizeCount === 1) {
                    foreach ($rawAllocations as &$row) {
                        $row['size_option_id'] = $effectiveSizeIds[0];
                    }
                    unset($row);
                }

                $lines = StockPurchaseService::mergeAllocationLines($rawAllocations);

                if ($lines === [] && ! empty($data['initial_stock_branch_id'])) {
                    $lines = StockPurchaseService::mergeAllocationLines([
                        [
                            'branch_id' => (int) $data['initial_stock_branch_id'],
                            'quantity' => $initialStock,
                            'color_option_id' => $colorCount === 1 ? $effectiveColorIds[0] : null,
                            'size_option_id' => $sizeCount === 1 ? $effectiveSizeIds[0] : null,
                        ],
                    ]);
                }

                if ($lines === []) {
                    throw ValidationException::withMessages([
                        'initial_stock_allocations' => ['Add at least one branch and quantity for initial stock.'],
                    ]);
                }

                if ($colorCount >= 2) {
                    foreach ($lines as $line) {
                        if (empty($line['color_option_id'])) {
                            throw ValidationException::withMessages([
                                'initial_stock_allocations' => ['When this product has multiple colors, each row must include a color.'],
                            ]);
                        }
                        if (! in_array((int) $line['color_option_id'], $effectiveColorIds, true)) {
                            throw ValidationException::withMessages([
                                'initial_stock_allocations' => ['Each color must be one of the colors selected for this product.'],
                            ]);
                        }
                    }
                }

                if ($sizeCount >= 2) {
                    foreach ($lines as $line) {
                        if (empty($line['size_option_id'])) {
                            throw ValidationException::withMessages([
                                'initial_stock_allocations' => ['When this product has multiple sizes, each row must include a size.'],
                            ]);
                        }
                        if (! in_array((int) $line['size_option_id'], $effectiveSizeIds, true)) {
                            throw ValidationException::withMessages([
                                'initial_stock_allocations' => ['Each size must be one of the sizes selected for this product.'],
                            ]);
                        }
                    }
                }

                $allocated = array_sum(array_column($lines, 'quantity'));
                if ($allocated !== $initialStock) {
                    throw ValidationException::withMessages([
                        'initial_stock_allocations' => ["Quantities per branch (and color/size) must add up to Stock ({$initialStock}); they add up to {$allocated}."],
                    ]);
                }

                app(StockPurchaseService::class)->createDistributed([
                    'product_id' => $product->id,
                    'unit_cost' => $initialCost,
                    'sale_price' => (float) $data['price'],
                    'vendor' => $data['initial_stock_vendor'] ?? null,
                    'date' => $data['initial_stock_date'] ?? now()->toDateString(),
                    'bank_account_id' => $data['initial_stock_bank_account_id'],
                    'lines' => $lines,
                ]);
            }

            return $product->fresh();
        });
    }

    private static function nullableOptionId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * Filament may pass a relative path (string), a keyed array, or an unsaved Livewire upload.
     * Persist uploads to the public disk so the path stored on the product matches FileUpload config.
     */
    private function normalizeProductImage(mixed $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        if (is_string($image)) {
            return $image;
        }

        if (is_array($image)) {
            foreach ($image as $value) {
                $normalized = $this->normalizeProductImage($value);

                if ($normalized !== null) {
                    return $normalized;
                }
            }

            return null;
        }

        if ($image instanceof TemporaryUploadedFile || $image instanceof UploadedFile) {
            /** @var TemporaryUploadedFile|UploadedFile $image */
            return $image->store('products', 'public');
        }

        return null;
    }
}
