<?php

namespace App\Filament\Resources\PaymentTypeResource\Pages;

use App\Filament\Resources\PaymentTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditPaymentType extends EditRecord
{
    protected static string $resource = PaymentTypeResource::class;

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
    }
}
