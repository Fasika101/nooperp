<?php

namespace App\Filament\Resources\BankAccountResource\Pages;

use App\Filament\Resources\BankAccountResource;
use App\Models\BankAccount;
use Filament\Resources\Pages\EditRecord;

class EditBankAccount extends EditRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record->is_global) {
            $record->branches()->detach();
            $record->branch_id = null;
        } else {
            $record->branch_id = $record->branches()->orderBy('branches.id')->first()?->id;
        }
        $record->saveQuietly();

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
