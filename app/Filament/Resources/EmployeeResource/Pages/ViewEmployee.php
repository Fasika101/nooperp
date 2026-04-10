<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        $record = static::getResource()::resolveRecordRouteBinding($key, function (Builder $query): Builder {
            return $query->with([
                'department',
                'jobPosition',
                'manager',
                'branch',
                'user',
            ]);
        });

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel(Employee::class, [(string) $key]);
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
