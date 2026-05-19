<?php

namespace App\Console\Commands;

use App\Models\BankTransaction;
use App\Models\Order;
use Illuminate\Console\Command;

class PurgeOrderBankTransactionsCommand extends Command
{
    protected $signature = 'bank:purge-order-transactions
                            {--dry-run : Preview what would be deleted without making any changes}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete duplicate order-level bank transactions (reference_type = Order). '
        .'Bank balances are auto-corrected by BankTransactionObserver on delete.';

    public function handle(): int
    {
        $transactions = BankTransaction::query()
            ->where('reference_type', Order::class)
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No order-level bank transactions found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Bank Account ID', 'Amount', 'Date', 'Description'],
            $transactions->map(fn ($t) => [
                $t->id,
                $t->bank_account_id,
                $t->amount,
                $t->date,
                $t->description,
            ])
        );

        if ($this->option('dry-run')) {
            $this->warn("Dry run: {$transactions->count()} transaction(s) would be deleted.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$transactions->count()} order-level bank transaction(s)? Bank balances will be auto-corrected.")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($transactions as $transaction) {
            $transaction->delete();
            $deleted++;
        }

        $this->info("Done. {$deleted} transaction(s) deleted and bank balances corrected.");

        return self::SUCCESS;
    }
}
