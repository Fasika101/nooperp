<?php

namespace App\Filament\Resources\OpticalLensNoPrescriptions\Pages;

use App\Filament\Resources\OpticalLensNoPrescriptions\OpticalLensNoPrescriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOpticalLensNoPrescriptions extends ManageRecords
{
    protected static string $resource = OpticalLensNoPrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
