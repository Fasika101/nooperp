<?php

namespace App\Observers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Expense;
use Illuminate\Validation\ValidationException;

class ExpenseObserver
{
    public function creating(Expense $expense): void
    {
        $this->ensureSufficientBalance($expense);
    }

    public function created(Expense $expense): void
    {
        $this->syncWithdrawalTransaction($expense);
    }

    public function updating(Expense $expense): void
    {
        $this->ensureSufficientBalance($expense);
    }

    public function updated(Expense $expense): void
    {
        $this->syncWithdrawalTransaction($expense);
    }

    public function deleting(Expense $expense): void
    {
        $this->getLinkedTransaction($expense)?->delete();
    }

    protected function ensureSufficientBalance(Expense $expense): void
    {
        $account = $this->resolveAccount($expense);
        $amount = (float) $expense->amount;

        if (! $account || $amount <= 0) {
            return;
        }

        if ($expense->branch_id && ! $account->isUsableAtBranch((int) $expense->branch_id)) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'The selected account is not available for the chosen branch.',
            ]);
        }

        $existingTransaction = $expense->exists ? $this->getLinkedTransaction($expense) : null;
        $availableBalance = (float) $account->current_balance;

        if ($existingTransaction && (int) $existingTransaction->bank_account_id === (int) $account->id) {
            $availableBalance += (float) $existingTransaction->amount;
        }

        if ($availableBalance < $amount) {
            throw ValidationException::withMessages([
                'bank_account_id' => "Insufficient balance in {$account->name}.",
            ]);
        }

        $expense->bank_account_id = $account->id;
    }

    protected function syncWithdrawalTransaction(Expense $expense): void
    {
        $account = $this->resolveAccount($expense);
        $amount = (float) $expense->amount;
        $transaction = $this->getLinkedTransaction($expense);

        if (! $account || $amount <= 0) {
            $transaction?->delete();

            return;
        }

        $expense->loadMissing('expenseType');

        $attributes = [
            'bank_account_id' => $account->id,
            'branch_id' => $expense->branch_id ?? $account->getSingleBranchIdForFallback(),
            'date' => $expense->date->toDateString(),
            'type' => BankTransaction::TYPE_WITHDRAWAL,
            'amount' => $amount,
            'description' => ($expense->expenseType?->name ?? 'Expense').($expense->vendor ? " - {$expense->vendor}" : ''),
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
        ];

        if ($transaction) {
            $transaction->update($attributes);

            return;
        }

        BankTransaction::create($attributes);
    }

    protected function getLinkedTransaction(Expense $expense): ?BankTransaction
    {
        return BankTransaction::query()
            ->where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)
            ->first();
    }

    protected function resolveAccount(Expense $expense): ?BankAccount
    {
        if ($expense->bank_account_id) {
            return BankAccount::query()->find($expense->bank_account_id);
        }

        return BankAccount::getDefaultAccount();
    }
}
