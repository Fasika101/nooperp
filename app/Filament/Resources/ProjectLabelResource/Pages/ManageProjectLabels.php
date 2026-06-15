<?php

namespace App\Filament\Resources\ProjectLabelResource\Pages;

use App\Filament\Resources\ProjectLabelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectLabels extends ManageRecords
{
    protected static string $resource = ProjectLabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
