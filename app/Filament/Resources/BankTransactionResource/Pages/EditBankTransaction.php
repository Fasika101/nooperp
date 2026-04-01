<?php

namespace App\Filament\Resources\BankTransactionResource\Pages;

use App\Filament\Resources\BankTransactionResource;
use App\Models\BankTransaction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditBankTransaction extends EditRecord
{
    protected static string $resource = BankTransactionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['destination_bank_account_id']);

        if (($data['type'] ?? null) === BankTransaction::TYPE_TRANSFER || $this->getRecord()->isTransferEntry()) {
            throw ValidationException::withMessages([
                'type' => 'Transfer entries cannot be edited directly. Delete and recreate the transfer instead.',
            ]);
        }

        return $data;
    }
}
