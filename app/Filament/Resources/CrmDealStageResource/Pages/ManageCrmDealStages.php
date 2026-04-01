<?php

namespace App\Filament\Resources\CrmDealStageResource\Pages;

use App\Filament\Resources\CrmDealStageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCrmDealStages extends ManageRecords
{
    protected static string $resource = CrmDealStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
