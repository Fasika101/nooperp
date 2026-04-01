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
        $product = $branchProductStock->product()->first();

        if (! $product) {
            return;
        }

        $totalStock = (int) BranchProductStock::query()
            ->where('product_id', $product->id)
            ->sum('quantity');

        Product::query()->whereKey($product->id)->update([
            'stock' => $totalStock,
        ]);
    }
}
