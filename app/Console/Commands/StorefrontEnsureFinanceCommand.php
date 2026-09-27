<?php

namespace App\Console\Commands;

use App\Services\Shop\StorefrontFinance;
use Illuminate\Console\Command;

class StorefrontEnsureFinanceCommand extends Command
{
    protected $signature = 'storefront:ensure-finance';

    protected $description = 'Create/link the Website orders bank account and Chapa payment type';

    public function handle(): int
    {
        $account = StorefrontFinance::bankAccount();
        $type = StorefrontFinance::paymentType();

        $this->info('Bank account: #'.$account->id.' — '.$account->name);
        $this->info('Payment type: #'.$type->id.' — '.$type->name);
        $this->comment('Linked bank_account_id: '.$type->bank_account_id);
        $this->newLine();
        $this->line('Optional .env overrides:');
        $this->line('  STOREFRONT_BANK_ACCOUNT_ID='.$account->id);
        $this->line('  STOREFRONT_PAYMENT_TYPE_ID='.$type->id);

        return self::SUCCESS;
    }
}
