<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountsReceivableService
{
    /**
     * Record cash/bank collection against an order with an open balance.
     *
     * @throws ValidationException
     */
    public function recordCollectionPayment(Order $order, int $paymentTypeId, float $amount): Payment
    {
        if ($order->balance_due <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['This order has no balance due.'],
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than zero.'],
            ]);
        }

        $type = PaymentType::query()->where('is_active', true)->whereKey($paymentTypeId)->first();
        if (! $type) {
            throw ValidationException::withMessages([
                'payment_type_id' => ['Invalid payment type.'],
            ]);
        }

        if ($type->is_accounts_receivable) {
            throw ValidationException::withMessages([
                'payment_type_id' => ['Choose a cash or bank payment type to record a collection.'],
            ]);
        }

        if ($order->branch_id && ! $type->isUsableAtBranch((int) $order->branch_id)) {
            throw ValidationException::withMessages([
                'payment_type_id' => ['This payment type is not available for this order’s branch.'],
            ]);
        }

        if ($amount > (float) $order->balance_due + 0.01) {
            throw ValidationException::withMessages([
                'amount' => ['Amount cannot exceed the balance due ('.$order->balance_due.').'],
            ]);
        }

        return DB::transaction(function () use ($order, $type, $amount) {
            return Payment::query()->create([
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'payment_type_id' => $type->id,
                'amount' => round($amount, 2),
                'payment_method' => $type->name,
                'status' => 'completed',
            ]);
        });
    }
}
