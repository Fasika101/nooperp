<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductCreationService
{
    public function create(array $data): Product
    {
        $initialStock = (int) ($data['stock'] ?? 0);
        $initialCost = (float) ($data['cost_price'] ?? 0);

        return DB::transaction(function () use ($data, $initialStock, $initialCost) {
            $sizeIds = Product::validatedOptionIdsForType((array) ($data['size_option_ids'] ?? []), ProductOption::TYPE_SIZE);
            $colorIds = Product::validatedOptionIdsForType((array) ($data['color_option_ids'] ?? []), ProductOption::TYPE_COLOR);

            $product = Product::query()->create([
                'image' => $this->normalizeProductImage($data['image'] ?? null),
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'size_option_id' => $sizeIds[0] ?? ($data['size_option_id'] ?? null),
                'color_option_id' => $colorIds[0] ?? ($data['color_option_id'] ?? null),
                'gender_option_id' => $data['gender_option_id'] ?? null,
                'material_option_id' => $data['material_option_id'] ?? null,
                'shape_option_id' => $data['shape_option_id'] ?? null,
                'brand_option_id' => $data['brand_option_id'] ?? null,
                'original_price' => $data['original_price'] ?? null,
                'cost_price' => $initialStock > 0 ? 0 : ($data['cost_price'] ?? null),
                'price' => $data['price'],
                'stock' => 0,
            ]);

            if ($initialStock > 0 && $initialCost > 0) {
                app(StockPurchaseService::class)->create([
                    'product_id' => $product->id,
                    'branch_id' => $data['initial_stock_branch_id'],
                    'quantity' => $initialStock,
                    'unit_cost' => $initialCost,
                    'sale_price' => (float) $data['price'],
                    'vendor' => $data['initial_stock_vendor'] ?? null,
                    'date' => $data['initial_stock_date'] ?? now()->toDateString(),
                    'bank_account_id' => $data['initial_stock_bank_account_id'],
                ]);
            }

            $product->attachedProductOptions()->sync(array_values(array_unique(array_merge($sizeIds, $colorIds))));

            return $product->fresh();
        });
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
