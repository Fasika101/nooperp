<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        $record = static::getResource()::resolveRecordRouteBinding($key, function (Builder $query): Builder {
            return $query->with(['branch', 'bankAccount', 'expenseType', 'employee']);
        });

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel(Expense::class, [(string) $key]);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
