<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BranchProductStock;
use App\Models\BranchStockTransfer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchStockTransferService
{
    /**
     * @param  array{product_id: int, product_variant_id?: ?int, from_branch_id: int, to_branch_id: int, quantity: int, note?: ?string, user_id?: ?int}  $data
     */
    public function transfer(array $data): BranchStockTransfer
    {
        $fromId = (int) $data['from_branch_id'];
        $toId = (int) $data['to_branch_id'];
        $qty = (int) $data['quantity'];
        $variantId = isset($data['product_variant_id']) ? (int) $data['product_variant_id'] : null;

        if ($fromId === $toId) {
            throw ValidationException::withMessages([
                'to_branch_id' => ['Choose a different destination branch.'],
            ]);
        }

        if ($qty <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        return DB::transaction(function () use ($data, $fromId, $toId, $qty, $variantId): BranchStockTransfer {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);

            $variants = ProductVariant::query()->where('product_id', $product->id)->orderBy('id')->get();

            $variant = null;
            if ($variantId !== null && $variantId > 0) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->whereKey($variantId)
                    ->first();
            }
            if (! $variant && $variants->count() === 1) {
                $variant = $variants->first();
            }
            if (! $variant && $variants->isEmpty()) {
                $variant = ProductVariant::findOrCreateForProduct($product->id, null, null);
            }
            if (! $variant) {
                throw ValidationException::withMessages([
                    'product_variant_id' => ['Select a variant (color / size) to transfer.'],
                ]);
            }

            $fromStock = BranchProductStock::query()
                ->where('branch_id', $fromId)
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            $available = (int) ($fromStock?->quantity ?? 0);
            if ($available < $qty || ! $fromStock) {
                throw ValidationException::withMessages([
                    'quantity' => ["Only {$available} unit(s) available at the source branch for this variant."],
                ]);
            }

            $sourceAvg = (float) ($fromStock->avg_cost ?? ($product->cost_price ?? 0));

            $toStock = BranchProductStock::query()->firstOrNew([
                'branch_id' => $toId,
                'product_variant_id' => $variant->id,
            ]);

            $toQtyBefore = (int) ($toStock->exists ? $toStock->quantity : 0);
            $toAvgBefore = (float) ($toStock->avg_cost ?? $sourceAvg);

            $fromStock->decrement('quantity', $qty);

            $toQtyAfter = $toQtyBefore + $qty;
            $toAvgAfter = $toQtyAfter > 0
                ? round((($toQtyBefore * $toAvgBefore) + ($qty * $sourceAvg)) / $toQtyAfter, 2)
                : $sourceAvg;

            $toStock->fill([
                'quantity' => $toQtyAfter,
                'avg_cost' => $toAvgAfter,
            ])->save();

            return BranchStockTransfer::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'from_branch_id' => $fromId,
                'to_branch_id' => $toId,
                'quantity' => $qty,
                'note' => $data['note'] ?? null,
                'user_id' => $data['user_id'] ?? null,
            ]);
        });
    }
}
