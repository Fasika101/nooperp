<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\ExpenseType;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $sid = ExpenseType::salariesTypeId();
        if (! $sid || (int) ($data['expense_type_id'] ?? 0) !== $sid) {
            $data['employee_id'] = null;
        }
        $pid = ExpenseType::affiliatePayoutTypeId();
        if (! $pid || (int) ($data['expense_type_id'] ?? 0) !== $pid) {
            $data['affiliate_id'] = null;
        }

        return $data;
    }
}
