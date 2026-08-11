<?php

namespace App\Filament\Resources\LensSolutionTypes\Pages;

use App\Filament\Resources\LensSolutionTypes\LensSolutionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLensSolutionTypes extends ManageRecords
{
    protected static string $resource = LensSolutionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
