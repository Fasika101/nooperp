<?php

namespace App\Observers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Product;
use App\Models\ProductDeletionLog;
use App\Models\StockPurchase;

class ProductObserver
{
    /**
     * When a product is deleted:
     *  1. Refund only the remaining inventory value (stock × cost_price) as a deposit
     *     rather than reversing every historical purchase expense.
     *  2. Write a ProductDeletionLog snapshot for the admin audit trail.
     */
    public function deleting(Product $product): void
    {
        $remainingStock = (int) $product->stock;
        $costPrice = (float) $product->cost_price;
        $refundAmount = ($remainingStock > 0 && $costPrice > 0)
            ? round($remainingStock * $costPrice, 2)
            : 0.0;

        // Use the most recently used bank account for this product, falling back to the default.
        $bankAccountId = StockPurchase::query()
            ->where('product_id', $product->getKey())
            ->whereNotNull('bank_account_id')
            ->latest()
            ->value('bank_account_id');

        if (! $bankAccountId) {
            $bankAccountId = BankAccount::getDefaultAccount()?->id;
        }

        if ($refundAmount > 0 && $bankAccountId) {
            BankTransaction::create([
                'bank_account_id' => $bankAccountId,
                'date' => now()->toDateString(),
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $refundAmount,
                'description' => "Inventory refund – \"{$product->name}\" deleted ({$remainingStock} units × {$costPrice})",
            ]);
        }

        ProductDeletionLog::create([
            'product_name' => $product->name,
            'product_cost_price' => $costPrice ?: null,
            'remaining_stock' => $remainingStock,
            'refunded_amount' => $refundAmount,
            'bank_account_id' => ($refundAmount > 0) ? $bankAccountId : null,
            'deleted_by_user_id' => auth()->id(),
            'notes' => $product->deletion_notes ?? null,
        ]);
    }
}
