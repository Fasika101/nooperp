<?php

namespace App\Filament\Resources\RepairTypes\Pages;

use App\Filament\Resources\RepairTypes\RepairTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRepairTypes extends ManageRecords
{
    protected static string $resource = RepairTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
