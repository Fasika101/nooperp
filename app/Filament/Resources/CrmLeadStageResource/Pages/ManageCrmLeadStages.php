<?php

namespace App\Filament\Resources\CrmLeadStageResource\Pages;

use App\Filament\Resources\CrmLeadStageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCrmLeadStages extends ManageRecords
{
    protected static string $resource = CrmLeadStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
