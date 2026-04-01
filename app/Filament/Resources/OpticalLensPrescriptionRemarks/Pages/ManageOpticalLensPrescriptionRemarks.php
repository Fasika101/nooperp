<?php

namespace App\Filament\Resources\OpticalLensPrescriptionRemarks\Pages;

use App\Filament\Resources\OpticalLensPrescriptionRemarks\OpticalLensPrescriptionRemarkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOpticalLensPrescriptionRemarks extends ManageRecords
{
    protected static string $resource = OpticalLensPrescriptionRemarkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
