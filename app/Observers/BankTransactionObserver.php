<?php

namespace App\Observers;

use App\Models\BankAccount;
use App\Models\BankTransaction;

class BankTransactionObserver
{
    /**
     * @var array<int|string, array{bank_account_id:int|null, amount:float, type:string|null}>
     */
    protected static array $originalStates = [];

    /**
     * @var array<int, true>
     */
    protected static array $deletingPairIds = [];

    public function creating(BankTransaction $transaction): void
    {
        if (! $transaction->branch_id && $transaction->bank_account_id) {
            $transaction->branch_id = BankAccount::query()
                ->whereKey($transaction->bank_account_id)
                ->value('branch_id');
        }
    }

    public function created(BankTransaction $transaction): void
    {
        $this->applyImpact($transaction->bank_account_id, $transaction->getBalanceImpact());
    }

    public function updating(BankTransaction $transaction): void
    {
        if (! $transaction->branch_id && $transaction->bank_account_id) {
            $transaction->branch_id = BankAccount::query()
                ->whereKey($transaction->bank_account_id)
                ->value('branch_id');
        }

        self::$originalStates[$this->getObserverKey($transaction)] = [
            'bank_account_id' => $transaction->getOriginal('bank_account_id'),
            'amount' => (float) $transaction->getOriginal('amount'),
            'type' => $transaction->getOriginal('type'),
        ];
    }

    public function updated(BankTransaction $transaction): void
    {
        $key = $this->getObserverKey($transaction);
        $original = self::$originalStates[$key] ?? null;
        unset(self::$originalStates[$key]);

        if (! $original) {
            return;
        }

        $originalImpact = ($original['type'] ?? null) === BankTransaction::TYPE_DEPOSIT
            ? $original['amount']
            : -$original['amount'];

        $newImpact = $transaction->getBalanceImpact();

        if ($original['bank_account_id'] === $transaction->bank_account_id) {
            $delta = $newImpact - $originalImpact;
            $this->applyImpact($transaction->bank_account_id, $delta);

            return;
        }

        $this->applyImpact($original['bank_account_id'], -$originalImpact);
        $this->applyImpact($transaction->bank_account_id, $newImpact);
    }

    public function deleting(BankTransaction $transaction): void
    {
        if (! $transaction->linked_transaction_id) {
            return;
        }

        $transactionId = $transaction->getKey();
        if (! $transactionId || isset(self::$deletingPairIds[$transactionId])) {
            return;
        }

        self::$deletingPairIds[$transactionId] = true;
        self::$deletingPairIds[$transaction->linked_transaction_id] = true;

        try {
            $transaction->loadMissing('linkedTransaction');
            $transaction->linkedTransaction?->delete();
        } finally {
            unset(self::$deletingPairIds[$transactionId], self::$deletingPairIds[$transaction->linked_transaction_id]);
        }
    }

    public function deleted(BankTransaction $transaction): void
    {
        $this->applyImpact($transaction->bank_account_id, -$transaction->getBalanceImpact());
    }

    protected function applyImpact(?int $accountId, float $impact): void
    {
        if (! $accountId || $impact === 0.0) {
            return;
        }

        $query = BankAccount::query()->whereKey($accountId);

        if ($impact > 0) {
            $query->increment('current_balance', $impact);

            return;
        }

        $query->decrement('current_balance', abs($impact));
    }

    protected function getObserverKey(BankTransaction $transaction): int|string
    {
        return $transaction->getKey() ?? spl_object_id($transaction);
    }
}
