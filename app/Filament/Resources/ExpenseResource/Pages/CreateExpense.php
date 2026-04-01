<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    public function mount(): void
    {
        parent::mount();
        $typeId = request()->query('expense_type_id');
        if (filled($typeId)) {
            $this->form->fill(['expense_type_id' => $typeId]);
        }
    }
}
