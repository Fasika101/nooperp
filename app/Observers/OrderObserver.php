<?php

namespace App\Observers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Order;

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
