<?php

declare(strict_types=1);

namespace App\Filament\Resources\BranchStockTransferResource\Pages;

use App\Filament\Resources\BranchStockTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBranchStockTransfers extends ListRecords
{
    protected static string $resource = BranchStockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
