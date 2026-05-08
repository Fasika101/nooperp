<?php

namespace App\Observers;

use App\Models\AffiliateCommissionSettlement;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BranchProductStock;
use App\Models\Order;
use App\Models\OrderDeletionLog;
use App\Models\ProductVariant;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->createDepositIfCompleted($order);
    }

    public function updated(Order $order): void
    {
        $this->createDepositIfCompleted($order);
    }

    /**
     * When an order is deleted:
     *  1. Restore branch stock for every line item.
     *  2. Delete all payments → PaymentObserver chain reverses bank transactions.
     *  3. Delete any order-level bank transaction directly.
     *  4. Reverse settled affiliate commissions where possible.
     *  5. Write an OrderDeletionLog snapshot.
     */
    public function deleting(Order $order): void
    {
        $order->loadMissing([
            'orderItems',
            'payments',
            'affiliateCommissionSettlements.expense.affiliateCommissionSettlements',
            'customer',
            'branch',
            'affiliate',
        ]);

        // 1. Restore stock
        $branchId = $order->branch_id;
        if ($branchId) {
            foreach ($order->orderItems as $item) {
                if (! $item->product_id || $item->is_service ?? false) {
                    continue;
                }

                $colorOptionId = $item->color_option_id ? (int) $item->color_option_id : null;
                $sizeOptionId = $item->size_option_id ? (int) $item->size_option_id : null;

                $variant = ProductVariant::query()
                    ->where('product_id', $item->product_id)
                    ->when(
                        $colorOptionId !== null,
                        fn ($q) => $q->where('color_option_id', $colorOptionId),
                        fn ($q) => $q->whereNull('color_option_id'),
                    )
                    ->when(
                        $sizeOptionId !== null,
                        fn ($q) => $q->where('size_option_id', $sizeOptionId),
                        fn ($q) => $q->whereNull('size_option_id'),
                    )
                    ->first();

                if (! $variant) {
                    continue;
                }

                $branchStock = BranchProductStock::query()
                    ->where('branch_id', $branchId)
                    ->where('product_variant_id', $variant->id)
                    ->first();

                if ($branchStock) {
                    $branchStock->increment('quantity', (int) $item->quantity);
                } else {
                    BranchProductStock::create([
                        'branch_id' => $branchId,
                        'product_variant_id' => $variant->id,
                        'quantity' => (int) $item->quantity,
                    ]);
                }
            }
        }

        // 2. Delete payments → PaymentObserver handles bank transaction reversal
        foreach ($order->payments as $payment) {
            $payment->delete();
        }

        // 3. Delete order-level bank transaction if it exists
        BankTransaction::query()
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->getKey())
            ->get()
            ->each(fn (BankTransaction $t) => $t->delete());

        // 4. Reverse settled affiliate commissions
        foreach ($order->affiliateCommissionSettlements as $settlement) {
            if ($settlement->expense_id && $settlement->expense) {
                $expense = $settlement->expense;
                $otherSettlementsCount = $expense->affiliateCommissionSettlements
                    ->where('order_id', '!=', $order->getKey())
                    ->count();

                if ($otherSettlementsCount === 0) {
                    // This expense only covers the deleted order — delete it entirely
                    // ExpenseObserver::deleting will remove the linked BankTransaction
                    $expense->delete();
                } else {
                    // Expense spans multiple orders — reduce it by this order's share
                    $newAmount = round(max(0, (float) $expense->amount - (float) $settlement->amount), 2);
                    if ($newAmount > 0) {
                        $expense->update(['amount' => $newAmount]);
                    } else {
                        $expense->delete();
                    }
                }
            }

            $settlement->delete();
        }

        // 5. Write deletion log
        $itemsSnapshot = $order->orderItems->map(fn ($item) => [
            'product_id' => $item->product_id,
            'name' => $item->display_name ?? $item->line_label,
            'quantity' => $item->quantity,
            'price' => (float) $item->price,
            'unit_cost' => (float) ($item->unit_cost ?? 0),
        ])->values()->all();

        OrderDeletionLog::create([
            'original_order_id' => $order->getKey(),
            'customer_name' => $order->customer?->name,
            'branch_name' => $order->branch?->name,
            'affiliate_name' => $order->affiliate?->name,
            'affiliate_commission_amount' => $order->affiliate_commission_amount ?? null,
            'total_amount' => $order->total_amount,
            'amount_paid' => $order->amount_paid,
            'payment_status' => $order->payment_status,
            'order_status' => $order->status,
            'items_snapshot' => $itemsSnapshot,
            'deleted_by_user_id' => auth()->id(),
            'notes' => $order->deletion_notes ?? null,
        ]);
    }

    protected function createDepositIfCompleted(Order $order): void
    {
        if ($order->status !== 'completed') {
            return;
        }

        if (BankTransaction::where('reference_type', Order::class)->where('reference_id', $order->id)->exists()) {
            return;
        }

        $account = BankAccount::getDefaultAccount();
        if (! $account) {
            return;
        }

        $amount = (float) $order->total_amount;
        if ($amount <= 0) {
            return;
        }

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'branch_id' => $order->branch_id,
            'date' => $order->created_at->toDateString(),
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => $amount,
            'description' => 'Sale #'.$order->id.' - '.($order->customer?->name ?? 'Walk-in'),
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);
    }
}
