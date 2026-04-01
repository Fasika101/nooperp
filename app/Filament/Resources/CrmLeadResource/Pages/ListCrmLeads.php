<?php

namespace App\Filament\Resources\CrmLeadResource\Pages;

use App\Filament\Resources\CrmLeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmLeads extends ListRecords
{
    protected static string $resource = CrmLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
