<?php

namespace App\Filament\Resources\CrmDealResource\Pages;

use App\Filament\Resources\CrmDealResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmDeals extends ListRecords
{
    protected static string $resource = CrmDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
