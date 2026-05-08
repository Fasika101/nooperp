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
        $payment->order?->syncPaymentTotals();
    }

    public function updated(Payment $payment): void
    {
        $this->syncPaymentTransaction($payment);
        $payment->order?->syncPaymentTotals();
    }

    public function deleted(Payment $payment): void
    {
        BankTransaction::query()
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->get()
            ->each(fn (BankTransaction $t) => $t->delete());

        $payment->order?->syncPaymentTotals();
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

        if ($payment->paymentType?->is_accounts_receivable) {
            $transaction?->delete();

            return;
        }

        $account = $payment->paymentType?->bankAccount ?? BankAccount::getDefaultAccount();
        $amount = (float) $payment->amount;

        if (! $account || $amount <= 0) {
            $transaction?->delete();

            return;
        }

        if ($transaction) {
            $transaction->update([
                'bank_account_id' => $account->id,
                'branch_id' => $payment->branch_id ?? $payment->order?->branch_id ?? $account->getSingleBranchIdForFallback(),
                'date' => $payment->order?->created_at?->toDateString() ?? now()->toDateString(),
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'description' => 'Sale #'.$payment->order_id.' - '.($payment->order?->customer?->name ?? 'Walk-in'),
            ]);

            return;
        }

        app(BankTransactionService::class)->createPaymentDeposit($payment);
    }
}
