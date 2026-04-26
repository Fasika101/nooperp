<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateCommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AffiliateCommissionSettlementService
{
    /**
     * @param  Collection<int, Order>  $orders
     */
    public function settle(Collection $orders, array $data): Expense
    {
        if ($orders->isEmpty()) {
            throw ValidationException::withMessages([
                'orders' => 'Select at least one order.',
            ]);
        }

        $orders = $orders->values();
        $first = $orders->first();
        $affiliateId = (int) $first->affiliate_id;
        $branchId = (int) $first->branch_id;

        foreach ($orders as $order) {
            if ((int) $order->affiliate_id !== $affiliateId) {
                throw ValidationException::withMessages([
                    'orders' => 'All selected orders must share the same affiliate.',
                ]);
            }
            if ((int) $order->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'orders' => 'All selected orders must share the same branch for one payout.',
                ]);
            }
            if ($order->status !== 'completed') {
                throw ValidationException::withMessages([
                    'orders' => "Order #{$order->id} is not completed.",
                ]);
            }
            if ((float) $order->affiliate_commission_amount <= 0) {
                throw ValidationException::withMessages([
                    'orders' => "Order #{$order->id} has no affiliate commission.",
                ]);
            }
            if ($this->remainingCommission($order) <= 0) {
                throw ValidationException::withMessages([
                    'orders' => "Order #{$order->id} has no remaining commission to settle.",
                ]);
            }
        }

        $payoutTypeId = ExpenseType::affiliatePayoutTypeId();
        if (! $payoutTypeId) {
            throw ValidationException::withMessages([
                'expense_type' => 'Affiliate payout expense type is missing. Run migrations.',
            ]);
        }

        $affiliate = Affiliate::query()->findOrFail($affiliateId);

        $lines = [];
        $total = 0.0;
        foreach ($orders as $order) {
            $remaining = $this->remainingCommission($order);
            $lines[] = ['order' => $order, 'amount' => $remaining];
            $total += $remaining;
        }
        $total = round($total, 2);

        return DB::transaction(function () use ($lines, $total, $data, $affiliate, $affiliateId, $branchId, $payoutTypeId) {
            $orderIds = collect($lines)->pluck('order.id')->all();
            Order::query()->whereIn('id', $orderIds)->lockForUpdate()->get();

            $description = isset($data['description']) && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : 'Affiliate commission settlement — orders: '.implode(', ', $orderIds);

            $expense = Expense::create([
                'date' => $data['date'] ?? now(),
                'amount' => $total,
                'expense_type_id' => $payoutTypeId,
                'bank_account_id' => (int) $data['bank_account_id'],
                'branch_id' => $branchId,
                'employee_id' => null,
                'affiliate_id' => $affiliateId,
                'vendor' => $affiliate->name,
                'description' => $description,
            ]);

            foreach ($lines as $line) {
                AffiliateCommissionSettlement::create([
                    'expense_id' => $expense->id,
                    'order_id' => $line['order']->id,
                    'amount' => $line['amount'],
                ]);
            }

            return $expense->fresh(['affiliate', 'bankAccount']);
        });
    }

    public function remainingCommission(Order $order): float
    {
        $commission = (float) $order->affiliate_commission_amount;
        $settled = (float) $order->affiliateCommissionSettlements()->sum('amount');

        return round(max(0, $commission - $settled), 2);
    }
}
