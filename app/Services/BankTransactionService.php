<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Payment;

class BankTransactionService
{
    public function createPaymentDeposit(Payment $payment): ?BankTransaction
    {
        if ($payment->status !== 'completed') {
            return null;
        }

        $existing = BankTransaction::query()
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $payment->loadMissing('order.customer', 'paymentType.bankAccount');

        $account = $payment->paymentType?->bankAccount ?? BankAccount::getDefaultAccount();
        if (! $account) {
            return null;
        }

        $amount = (float) $payment->amount;
        if ($amount <= 0) {
            return null;
        }

        return BankTransaction::query()->create([
            'bank_account_id' => $account->id,
            'branch_id' => $payment->branch_id ?: $account->branch_id,
            'date' => $payment->order?->created_at?->toDateString() ?? now()->toDateString(),
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => $amount,
            'description' => 'Sale #' . $payment->order_id . ' - ' . ($payment->order?->customer?->name ?? 'Walk-in'),
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);
    }

    public function createTransfer(BankAccount $sourceAccount, BankAccount $destinationAccount, float $amount, array $attributes = []): BankTransaction
    {
        if ($sourceAccount->is($destinationAccount)) {
            throw new \InvalidArgumentException('Source and destination accounts must be different.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than zero.');
        }

        $date = $attributes['date'] ?? now()->toDateString();
        $description = trim((string) ($attributes['description'] ?? ''));

        $withdrawal = BankTransaction::query()->create([
            'bank_account_id' => $sourceAccount->id,
            'branch_id' => $sourceAccount->branch_id,
            'date' => $date,
            'type' => BankTransaction::TYPE_WITHDRAWAL,
            'amount' => $amount,
            'description' => $description !== ''
                ? $description
                : "Transfer to {$destinationAccount->name}",
        ]);

        $deposit = BankTransaction::query()->create([
            'bank_account_id' => $destinationAccount->id,
            'branch_id' => $destinationAccount->branch_id,
            'date' => $date,
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => $amount,
            'description' => $description !== ''
                ? $description
                : "Transfer from {$sourceAccount->name}",
            'linked_transaction_id' => $withdrawal->id,
        ]);

        $withdrawal->forceFill([
            'linked_transaction_id' => $deposit->id,
        ])->saveQuietly();

        return $withdrawal->refresh();
    }
}
