<?php

namespace App\Observers;

use App\Models\BranchProductStock;
use App\Models\Product;

class BranchProductStockObserver
{
    public function created(BranchProductStock $branchProductStock): void
    {
        $this->syncProductStock($branchProductStock);
    }

    public function updated(BranchProductStock $branchProductStock): void
    {
        $this->syncProductStock($branchProductStock);
    }

    public function deleted(BranchProductStock $branchProductStock): void
    {
        $this->syncProductStock($branchProductStock);
    }

    protected function syncProductStock(BranchProductStock $branchProductStock): void
    {
        $variant = $branchProductStock->productVariant()->first();

        if (! $variant) {
            return;
        }

        $productId = (int) $variant->product_id;

        $totalStock = (int) BranchProductStock::query()
            ->whereHas('productVariant', fn ($q) => $q->where('product_id', $productId))
            ->sum('quantity');

        Product::query()->whereKey($productId)->update([
            'stock' => $totalStock,
        ]);
    }
}
