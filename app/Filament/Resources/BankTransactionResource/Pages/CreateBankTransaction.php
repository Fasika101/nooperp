<?php

namespace App\Filament\Resources\BankTransactionResource\Pages;

use App\Filament\Resources\BankTransactionResource;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\BankTransactionService;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;

class CreateBankTransaction extends CreateRecord
{
    protected static string $resource = BankTransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (($data['type'] ?? null) !== BankTransaction::TYPE_TRANSFER) {
            unset($data['destination_bank_account_id']);

            return parent::handleRecordCreation($data);
        }

        $sourceAccount = BankAccount::query()->findOrFail($data['bank_account_id']);
        $destinationAccount = BankAccount::query()->findOrFail($data['destination_bank_account_id']);

        return app(BankTransactionService::class)->createTransfer(
            $sourceAccount,
            $destinationAccount,
            (float) $data['amount'],
            [
                'date' => $data['date'],
                'description' => $data['description'] ?? null,
            ],
        );
    }
}
