<?php

namespace App\Observers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Payment;
use App\Services\BankTransactionService;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->syncPaymentTransaction($payment);
    }

    public function updated(Payment $payment): void
    {
        $this->syncPaymentTransaction($payment);
    }

    protected function syncPaymentTransaction(Payment $payment): void
    {
        $transaction = BankTransaction::query()
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->first();

        if ($payment->status !== 'completed') {
            $transaction?->delete();

            return;
        }

        $payment->loadMissing('order.customer', 'paymentType.bankAccount');

        $account = $payment->paymentType?->bankAccount ?? BankAccount::getDefaultAccount();
        $amount = (float) $payment->amount;

        if (! $account || $amount <= 0) {
            $transaction?->delete();

            return;
        }

        if ($transaction) {
            $transaction->update([
                'bank_account_id' => $account->id,
                'branch_id' => $payment->branch_id ?: $account->branch_id,
                'date' => $payment->order?->created_at?->toDateString() ?? now()->toDateString(),
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'description' => 'Sale #' . $payment->order_id . ' - ' . ($payment->order?->customer?->name ?? 'Walk-in'),
            ]);

            return;
        }

        app(BankTransactionService::class)->createPaymentDeposit($payment);
    }
}
