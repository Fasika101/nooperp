<?php

namespace App\Filament\Resources\JobPositionResource\Pages;

use App\Filament\Resources\JobPositionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobPositions extends ListRecords
{
    protected static string $resource = JobPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
