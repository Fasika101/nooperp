<?php

namespace App\Filament\Resources\BankAccountResource\Pages;

use App\Filament\Resources\BankAccountResource;
use App\Models\BankAccount;
use Filament\Resources\Pages\CreateRecord;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function afterCreate(): void
    {
        $this->ensureSingleDefaultAccount();
    }

    protected function ensureSingleDefaultAccount(): void
    {
        $record = $this->getRecord();
        if ($record->is_default) {
            BankAccount::where('id', '!=', $record->id)->update(['is_default' => false]);
        }
    }
}
