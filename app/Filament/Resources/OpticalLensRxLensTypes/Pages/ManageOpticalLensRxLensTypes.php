<?php

namespace App\Filament\Resources\OpticalLensRxLensTypes\Pages;

use App\Filament\Resources\OpticalLensRxLensTypes\OpticalLensRxLensTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOpticalLensRxLensTypes extends ManageRecords
{
    protected static string $resource = OpticalLensRxLensTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
