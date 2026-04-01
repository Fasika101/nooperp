<?php

namespace App\Filament\Resources\ProjectTaskStageResource\Pages;

use App\Filament\Resources\ProjectTaskStageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectTaskStages extends ManageRecords
{
    protected static string $resource = ProjectTaskStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
