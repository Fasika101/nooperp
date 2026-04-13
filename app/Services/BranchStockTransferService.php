<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BranchProductStock;
use App\Models\BranchStockTransfer;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchStockTransferService
{
    /**
     * @param  array{product_id: int, from_branch_id: int, to_branch_id: int, quantity: int, note?: ?string, user_id?: ?int}  $data
     */
    public function transfer(array $data): BranchStockTransfer
    {
        $fromId = (int) $data['from_branch_id'];
        $toId = (int) $data['to_branch_id'];
        $qty = (int) $data['quantity'];

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

        return DB::transaction(function () use ($data, $fromId, $toId, $qty): BranchStockTransfer {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);

            $fromStock = BranchProductStock::query()
                ->where('branch_id', $fromId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            $available = (int) ($fromStock?->quantity ?? 0);
            if ($available < $qty || ! $fromStock) {
                throw ValidationException::withMessages([
                    'quantity' => ["Only {$available} unit(s) available at the source branch."],
                ]);
            }

            $sourceAvg = (float) ($fromStock->avg_cost ?? ($product->cost_price ?? 0));

            $toStock = BranchProductStock::query()->firstOrNew([
                'branch_id' => $toId,
                'product_id' => $product->id,
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
                'from_branch_id' => $fromId,
                'to_branch_id' => $toId,
                'quantity' => $qty,
                'note' => $data['note'] ?? null,
                'user_id' => $data['user_id'] ?? null,
            ]);
        });
    }
}
